<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Workflow.revision
 *
 * @copyright   (C) 2026 Herman Peeren, Yepr.
 * @license     GNU General Public License version 3; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Yepr\Plugin\Workflow\Revision\Extension\Revision;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
	        $container->lazy(Revision::class, function (Container $container) {
                $plugin     = new Revision(
                    (array) PluginHelper::getPlugin('workflow', 'revision')
                );
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));
                return $plugin;
            })
        );
    }
};
