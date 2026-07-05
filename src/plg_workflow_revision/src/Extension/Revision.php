<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Workflow.revision
 *
 * @copyright   (C) 2026 Herman Peeren, Yepr
 * @license     GNU General Public License version 3; see LICENSE.txt
 */

namespace Yepr\Plugin\Workflow\Revision\Extension;

use Joomla\CMS\Event\Model;
use Joomla\CMS\Event\Workflow\WorkflowTransitionEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Workflow\WorkflowPluginTrait;
use Joomla\CMS\Workflow\WorkflowServiceInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Workflow Revision Plugin
 *
 */
final class Revision extends CMSPlugin implements SubscriberInterface
{
    use WorkflowPluginTrait;
    use DatabaseAwareTrait;

    /**
     * The id of the Revision Draft category (as set in the parameters of the plugin).
     *
     * @var    integer
     */
    private $revisionDraftCategoryId = 0;


    /**
     * The id of the Revision Review category (as set in the parameters of the plugin).
     *
     * @var    integer
     */
    private $revisionReviewCategoryId = 0;


    /**
     * The id of the current user.
     *
     * @var    integer
     */
    private $currentUserId = 0;


    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     */
    protected $autoloadLanguage = true;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return   array
     *
     * @since   4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepareForm'      => 'onContentPrepareForm',
            'onWorkflowAfterTransition' => 'onWorkflowAfterTransition',
        ];
    }

    /**
     * Add Revision-specific action-options to the transition form.
     *
     * @param   Model\PrepareFormEvent   $event  The event
     *
     * @return   boolean
     */
    public function onContentPrepareForm(Model\PrepareFormEvent $event)
    {
        $form    = $event->getForm();
        $data    = $event->getData();
        $context = $form->getName();

        // Extend the transition form
        if ($context === 'com_workflow.transition') {
            $this->enhanceWorkflowTransitionForm($form, $data);
        }

        return true;
    }

    /**
     * Copy original article and put the copied article in the correct revision category.
     *
     * @param   WorkflowTransitionEvent  $event  The workflow event being processed.
     *
     * @return   void
     */
    public function onWorkflowAfterTransition(WorkflowTransitionEvent $event)
    {
        $context = $event->getArgument('extension');
        // should check if valid context com_extensionname.tablename and there must be a category id in the table
        $extensionName = $event->getArgument('extensionName');
        $transition = $event->getArgument('transition');
        $pks = $event->getArgument('pks');

        if (!$this->isSupported($context)) {
            return;
        }

        // Process transition if we have a revision
        if ($revisionCategory = $transition->options['revision_category']) {
            // Initialisation
            // Set the id of the Revision Draft and the Revision Review category from the plugin's parameters
            $this->revisionDraftCategoryId  = $this->params->get('revision_draft_category_id');
            $this->revisionReviewCategoryId = $this->params->get('revision_review_category_id');
            // Set the current user id; this user will edit the draft
            $this->currentUserId = $this->getApplication()->getIdentity()->id;

            // For all item primary keys
            foreach ($pks as $pk) {
                // If this item is not in the revision table, $revisionInfoPk will be null
                $revisionInfoPk = $this->revisionInfoOriginal($context, $pk);

                // If we go to a Draft stage, we have to make a copy of the original if it doesn't exist yet
                if (($revisionCategory === 'draft')  && (is_null($revisionInfoPk))) {
                    // Make a copy of the original article
                    $this->copyItemToDraft($context, $pk);
                }
                else {
                    // If the item is approved after review, it can be copied back over the original item
                    if ($revisionCategory === 'approved') {
                        $this->copyReviewToOriginal($context, $pk);
                    }
                    else {
                        // Put in the Revision Draft or Revision Review Category
                        $this->setRevisionCategory($context, $pk, $revisionCategory);
                    }
                }
            }
        }
    }

    /**
     * Put an article with this primary key in the Revision Draft or Revision Review Category
     *
     * @param string  $context
     * @param integer $pk
     * @param string  $revisionCategory
     *
     * @return void
     */
    private function setRevisionCategory(string $context, int $pk, string $revisionCategory)
    {
        list($componentName, $tableName)  = explode('.', $context);
        $table = $this->getApplication()->bootComponent($componentName)->getMVCFactory()->getTable($tableName);
        $table->load($pk);
        $catColumn = $table->getColumnAlias('catid');
		switch($revisionCategory)
		{
			case 'draft':  $catcolumn = $this->revisionDraftCategoryId; break;
			case 'review': $catcolumn = $this->revisionReviewCategoryId; break;
        }
        if ($revisionCategory && $revisionCategory > 0) {
            $table->$catColumn = $revisionCategory;
        }
    }

    /**
     * Get the revision info of an original item id
     *
     * @param string  $context
     * @param integer $originalId
     *
     * @return object|null
     */
    private function revisionInfoOriginal(string $context, int $originalId)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
	        ->select($db->quoteName('*'))
	        ->from($db->quoteName('#__revision_copy_original'))
	        ->where($db->quoteName('context') . ' = :context')
	        ->where($db->quoteName('original_id') . ' = :originalId')
	        ->bind(':originalId', $context)
	        ->bind(':originalId', $originalId, ParameterType::INTEGER);
	    $db->setQuery($query);

	    return $db->loadObject();
    }

    /**
     * Get the revision info of a copied item id
     *
     * @param string $context
     * @param integer $pk
     *
     * @return object|null
     */
    private function revisionInfoCopy($context, $copiedId)
    {
	    $db = $this->getDatabase();
	    $query = $db->getQuery(true)
		    ->select($db->quoteName('*'))
		    ->from($db->quoteName('#__revision_copy_original'))
		    ->where($db->quoteName('context') . ' = :context')
		    ->where($db->quoteName('copy_id') . ' = :copyId')
		    ->bind(':copyId', $context)
		    ->bind(':copyId', $copyId, ParameterType::INTEGER);
	    $db->setQuery($query);

	    return $db->loadObject();
    }

    /**
     * Make a copy of an item to the Revision Draft category
     *
     * @param string  $context
     * @param integer $id
     *
     * @return void
     */
    private function copyItemToDraft($context, $originalId)
    {
		// Get the original item
	    list($componentName, $tableName)  = explode('.', $context);
	    $table = $this->getApplication()->bootComponent($componentName)->getMVCFactory()->getTable($tableName);
	    $table->load($originalId);

	    $catColumn       = $table->getColumnAlias('catid');
	    $createdByColumn = $table->getColumnAlias('created_by');
	    $createdColumn   = $table->getColumnAlias('created');

	    // Save to new item (id = 0) in Revision Draft category
	    // The item should have a created_by (int) and created (datetime) field, which should be set to current user and time.
	    // Ignore a created_by_alias; will stay the same in the original table.
	    $table->id               = 0;
		$table->$catColumn       = $this->revisionDraftCategoryId;
		$table->$createdByColumn = $this->currentUserId;
		$table->$createdColumn   = $date = Factory::getDate()->toSql();;

		$table->store();

	    // Get the new item ID of the copy
	    $copyId = $table->id;

		// Store info about the original and the new copy in the revision table
	    $revisionInfo = (object) [
		    'original_id' => $originalId,
		    'copy_id' => $copyId,
		    'context' => $context,
		    'modified_by' => $this->currentUserId,
		    'modified_time' => $table->$createdColumn
	    ];

	    $db = $this->getDatabase();
	    $db->insertObject('#__revision_copy_original', $revisionInfo);
    }

    /**
     * Put the copy of an item back to the original item
     *
     * @param  string  $context
     * @param  integer $id
     *
     *  @return void
     */
	private function copyReviewToOriginal($context, $copyId)
    {
        // Get the info about the revised item and the original item
	    $revisionInfo = $this->revisionInfoCopy($context, $copyId);

		// Get the table object

	    // Get the original item

	    // Get the revised item

	    // Copy the id, category, created_by and created from the original item to the revised + store

	    // Change the versions of the copy to the original

	    // Delete the revision item

    }


    /**
     * Check if the current plugin should execute workflow related activities
     *
     * @param   string  $context
     *
     * @return   boolean
     */
    protected function isSupported($context)
    {
        if (!$this->checkAllowedAndForbiddenlist($context)) {
            return false;
        }

        $parts = explode('.', $context);

        // We need at least the extension + view for loading the table fields
        if (\count($parts) < 2) {
            return false;
        }

        $component = $this->getApplication()->bootComponent($parts[0]);

        if (!$component instanceof WorkflowServiceInterface || !$component->isWorkflowActive($context)) {
            return false;
        }

        return true;
    }
}
