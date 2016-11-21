<?php
/*
 * This file is part of the Sales Report plugin
 *
 * Copyright (C) 2016 LOCKON CO.,LTD. All Rights Reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\SalesReport\ServiceProvider;

use Silex\Application as BaseApplication;
use Silex\ServiceProviderInterface;
use Plugin\SalesReport\Service\SalesReportService;
use Plugin\SalesReport\Form\Type\SalesReportType;
use Plugin\SalesReport\Utils\Version;
use Symfony\Component\Translation\Translator;

// include log functions (for 3.0.0 - 3.0.11)
require_once __DIR__.'/../log.php';

/**
 * Class SalesReportServiceProvider.
 */
class SalesReportServiceProvider implements ServiceProviderInterface
{
    /**
     * register.
     *
     * @param BaseApplication $app
     */
    public function register(BaseApplication $app)
    {
        // Routingを追加
        $admin = $app['config']['admin_route'];
        $app->match($admin.'/sales_report', '\\Plugin\\SalesReport\\Controller\\SalesReportController::index')
            ->bind('admin_sales_report');

        $app->match($admin.'/sales_report/term', '\\Plugin\\SalesReport\\Controller\\SalesReportController::term')
            ->bind('admin_sales_report_term');

        $app->match($admin.'/sales_report/age', '\\Plugin\\SalesReport\\Controller\\SalesReportController::age')
            ->bind('admin_sales_report_age');

        $app->match($admin.'/sales_report/product', '\\Plugin\\SalesReport\\Controller\\SalesReportController::product')
            ->bind('admin_sales_report_product');

        // Formの定義
        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new SalesReportType($app);

            return $types;
        }));

        // Serviceの定義
        $app['eccube.plugin.service.sales_report'] = $app->share(function () use ($app) {
            return new SalesReportService($app);
        });

        // initialize logger (for 3.0.0 - 3.0.8)
        if (!Version::isSupportGetInstanceFunction()) {
            eccube_log_init($app);
        }

        // メッセージ登録
        $app['translator'] = $app->share($app->extend('translator', function (Translator $translator, BaseApplication $app) {
            $file = __DIR__.'/../Resource/locale/message.'.$app['locale'].'.yml';
            if (file_exists($file)) {
                $translator->addResource('yaml', $file, $app['locale']);
            }

            return $translator;
        }));

        // サブナビの拡張
        $app['config'] = $app->share($app->extend('config', function ($config) {
            $nav = array(
                'id' => 'admin_sales_report',
                'name' => '売上集計',
                'has_child' => 'true',
                'icon' => 'cb-chart',
                'child' => array(
                    array(
                        'id' => 'admin_sales_report',
                        'url' => 'admin_sales_report',
                        'name' => '期間別集計',
                    ),
                    array(
                        'id' => 'admin_sales_report_product',
                        'url' => 'admin_sales_report_product',
                        'name' => '商品別集計',
                    ),
                    array(
                        'id' => 'admin_sales_report_age',
                        'url' => 'admin_sales_report_age',
                        'name' => '年代別集計',
                    ),
                ),
            );

            $config['nav'][] = $nav;

            return $config;
        }));
    }

    /**
     * boot.
     *
     * @param BaseApplication $app
     */
    public function boot(BaseApplication $app)
    {
    }
}
