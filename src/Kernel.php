<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App;

use App\DependencyInjection\AppExtension;
use App\DependencyInjection\Compiler\ExportServiceCompilerPass;
use App\DependencyInjection\Compiler\InvoiceServiceCompilerPass;
use App\DependencyInjection\Compiler\TwigContextCompilerPass;
use App\DependencyInjection\Compiler\WidgetCompilerPass;
use App\Export\ExportRepositoryInterface;
use App\Export\RendererInterface as ExportRendererInterface;
use App\Export\TimesheetExportInterface;
use App\Invoice\CalculatorInterface as InvoiceCalculator;
use App\Invoice\InvoiceItemRepositoryInterface;
use App\Invoice\NumberGeneratorInterface;
use App\Invoice\RendererInterface as InvoiceRendererInterface;
use App\Ldap\FormLoginLdapFactory;
use App\Plugin\PluginInterface;
use App\Saml\Security\SamlFactory;
use App\Timesheet\CalculatorInterface as TimesheetCalculator;
use App\Timesheet\Rounding\RoundingInterface;
use App\Timesheet\TrackingMode\TrackingModeInterface;
use App\Validator\Constraints\ProjectConstraint;
use App\Validator\Constraints\TimesheetConstraint;
use App\Widget\WidgetInterface;
use App\Widget\WidgetRendererInterface;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public const PLUGIN_DIRECTORY = '/var/plugins';
    public const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    public const TAG_PLUGIN = 'kimai.plugin';
    public const TAG_WIDGET = 'widget';
    public const TAG_WIDGET_RENDERER = 'widget.renderer';
    public const TAG_EXPORT_RENDERER = 'export.renderer';
    public const TAG_EXPORT_REPOSITORY = 'export.repository';
    public const TAG_INVOICE_RENDERER = 'invoice.renderer';
    public const TAG_INVOICE_NUMBER_GENERATOR = 'invoice.number_generator';
    public const TAG_INVOICE_CALCULATOR = 'invoice.calculator';
    public const TAG_INVOICE_REPOSITORY = 'invoice.repository';
    public const TAG_TIMESHEET_CALCULATOR = 'timesheet.calculator';
    public const TAG_TIMESHEET_VALIDATOR = 'timesheet.validator';
    public const TAG_TIMESHEET_EXPORTER = 'timesheet.exporter';
    public const TAG_TIMESHEET_TRACKING_MODE = 'timesheet.tracking_mode';
    public const TAG_TIMESHEET_ROUNDING_MODE = 'timesheet.rounding_mode';
    public const TAG_PROJECT_VALIDATOR = 'project.validator';

    public function getCacheDir()
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }

    public function getLogDir()
    {
        return $this->getProjectDir() . '/var/log';
    }

    protected function build(ContainerBuilder $container)
    {
        $container->registerForAutoconfiguration(TimesheetCalculator::class)->addTag(self::TAG_TIMESHEET_CALCULATOR);
        $container->registerForAutoconfiguration(ExportRendererInterface::class)->addTag(self::TAG_EXPORT_RENDERER);
        $container->registerForAutoconfiguration(ExportRepositoryInterface::class)->addTag(self::TAG_EXPORT_REPOSITORY);
        $container->registerForAutoconfiguration(InvoiceRendererInterface::class)->addTag(self::TAG_INVOICE_RENDERER);
        $container->registerForAutoconfiguration(NumberGeneratorInterface::class)->addTag(self::TAG_INVOICE_NUMBER_GENERATOR);
        $container->registerForAutoconfiguration(InvoiceCalculator::class)->addTag(self::TAG_INVOICE_CALCULATOR);
        $container->registerForAutoconfiguration(InvoiceItemRepositoryInterface::class)->addTag(self::TAG_INVOICE_REPOSITORY);
        $container->registerForAutoconfiguration(PluginInterface::class)->addTag(self::TAG_PLUGIN);
        $container->registerForAutoconfiguration(WidgetRendererInterface::class)->addTag(self::TAG_WIDGET_RENDERER);
        $container->registerForAutoconfiguration(WidgetInterface::class)->addTag(self::TAG_WIDGET);
        $container->registerForAutoconfiguration(TimesheetExportInterface::class)->addTag(self::TAG_TIMESHEET_EXPORTER);
        $container->registerForAutoconfiguration(TrackingModeInterface::class)->addTag(self::TAG_TIMESHEET_TRACKING_MODE);
        $container->registerForAutoconfiguration(RoundingInterface::class)->addTag(self::TAG_TIMESHEET_ROUNDING_MODE);
        $container->registerForAutoconfiguration(TimesheetConstraint::class)->addTag(self::TAG_TIMESHEET_VALIDATOR);
        $container->registerForAutoconfiguration(ProjectConstraint::class)->addTag(self::TAG_PROJECT_VALIDATOR);

        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new FormLoginLdapFactory());
        $extension->addSecurityListenerFactory(new SamlFactory());
    }

    public function registerBundles()
    {
        $contents = require $this->getProjectDir() . '/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                yield new $class();
            }
        }

        if ($this->environment === 'test' && getenv('TEST_WITH_BUNDLES') === false) {
            return;
        }

        // we can either define all kimai bundles hardcoded ...
        if (is_file($this->getProjectDir() . '/config/bundles-local.php')) {
            $contents = require $this->getProjectDir() . '/config/bundles-local.php';
            foreach ($contents as $class => $envs) {
                if (isset($envs['all']) || isset($envs[$this->environment])) {
                    yield new $class();
                }
            }
        } else {
            // ... or we load them dynamically from the plugins directory
            foreach ($this->getBundleClasses() as $pluginClass) {
                yield new $pluginClass();
            }
        }
    }

    private function getBundleClasses(): array
    {
        $pluginsDir = $this->getProjectDir() . self::PLUGIN_DIRECTORY;
        if (!file_exists($pluginsDir)) {
            return [];
        }

        $plugins = [];
        $finder = new Finder();
        $finder->ignoreUnreadableDirs()->directories()->name('*Bundle');
        /** @var SplFileInfo $bundleDir */
        foreach ($finder->in($pluginsDir) as $bundleDir) {
            $bundleName = $bundleDir->getRelativePathname();

            if (file_exists($bundleDir->getRealPath() . '/.disabled')) {
                continue;
            }

            $pluginClass = 'KimaiPlugin\\' . $bundleName . '\\' . $bundleName;
            if (!class_exists($pluginClass)) {
                continue;
            }

            $plugins[] = $pluginClass;
        }

        return $plugins;
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader)
    {
        $container->registerExtension(new AppExtension());

        $container->setParameter('container.autowiring.strict_mode', true);
        $container->setParameter('container.dumper.inline_class_loader', true);
        $confDir = $this->getProjectDir() . '/config';

        // if you want to prepend any config, you can do it here
        $loader->load($confDir . '/packages/local_before' . self::CONFIG_EXTS, 'glob');

        // using this one instead of $loader->load($confDir . '/packages/*' . self::CONFIG_EXTS, 'glob');
        // to get rid of the local.yaml from the list, we load it afterwards explicit
        $finder = (new Finder())
            ->files()
            ->in([$confDir . '/packages/'])
            ->name('*' . self::CONFIG_EXTS)
            ->notName('local*' . self::CONFIG_EXTS)
            ->depth('== 0')
            ->sortByName()
            ->followLinks()
        ;

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $loader->load($file->getPathname());
        }

        if (is_dir($confDir . '/packages/' . $this->environment)) {
            $loader->load($confDir . '/packages/' . $this->environment . '/**/*' . self::CONFIG_EXTS, 'glob');
        }
        $loader->load($confDir . '/packages/local' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/services' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/services-*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/services_' . $this->environment . self::CONFIG_EXTS, 'glob');

        $container->addCompilerPass(new TwigContextCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -1000);
        $container->addCompilerPass(new InvoiceServiceCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -1000);
        $container->addCompilerPass(new ExportServiceCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -1000);
        $container->addCompilerPass(new WidgetCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -1000);
    }

    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $confDir = $this->getProjectDir() . '/config';

        // load bundle specific route files
        if (is_dir($confDir . '/routes/')) {
            $routes->import($confDir . '/routes/*' . self::CONFIG_EXTS, '/', 'glob');
        }

        // load environment specific route files
        if (is_dir($confDir . '/routes/' . $this->environment)) {
            $routes->import($confDir . '/routes/' . $this->environment . '/**/*' . self::CONFIG_EXTS, '/', 'glob');
        }

        // load application routes
        $routes->import($confDir . '/routes' . self::CONFIG_EXTS, '/', 'glob');

        foreach ($this->bundles as $bundle) {
            if (strpos(\get_class($bundle), 'KimaiPlugin\\') !== false) {
                if (is_dir($bundle->getPath() . '/Resources/config/')) {
                    $routes->import($bundle->getPath() . '/Resources/config/routes' . self::CONFIG_EXTS, '/', 'glob');
                } elseif (is_dir($bundle->getPath() . '/config/')) {
                    $routes->import($bundle->getPath() . '/config/routes' . self::CONFIG_EXTS, '/', 'glob');
                }
            }
        }
    }
}
