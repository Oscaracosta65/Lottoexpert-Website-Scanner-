<?php
defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use LottoExpert\Component\LeSiteAudit\Administrator\Extension\LeSiteAuditComponent;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\LottoExpert\\Component\\LeSiteAudit'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\LottoExpert\\Component\\LeSiteAudit'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container): ComponentInterface {
                return new LeSiteAuditComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );
            }
        );
    }
};
