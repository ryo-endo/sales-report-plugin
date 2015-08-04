<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Plugin\SalesReport\ServiceProvider;

use Eccube\Application;
use Silex\Application as BaseApplication;
use Silex\ServiceProviderInterface;

class SalesReportServiceProvider implements ServiceProviderInterface
{
    public function register(BaseApplication $app)
    {
        // Routingを追加
        $admin = $app['config']['admin_dir'];
        $app->match($admin . 'sales_report', '\\Plugin\\SalesReport\\Controller\\SalesReportController::index')
            ->bind('admin_sales_report');

        $app->post($admin . 'sales_report/term', '\\Plugin\\SalesReport\\Controller\\SalesReportController::term')
            ->bind('admin_sales_report_term');

        $app->post($admin . 'sales_report/member', '\\Plugin\\SalesReport\\Controller\\SalesReportController::member')
            ->bind('admin_sales_report_member');

        $app->post($admin . 'sales_report/age', '\\Plugin\\SalesReport\\Controller\\SalesReportController::age')
            ->bind('admin_sales_report_age');

        $app->post($admin . 'sales_report/product', '\\Plugin\\SalesReport\\Controller\\SalesReportController::product')
            ->bind('admin_sales_report_product');

        // Formの定義
        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new \Plugin\SalesReport\Form\Type\SalesReportType();

            return $types;
        }));

        // Serviceの定義
        $app['eccube.plugin.service.sales_report'] = $app->share(function () use ($app) {
            return new \Plugin\SalesReport\Service\SalesReportService($app);
        });

        // サブナビの拡張
        $app['config'] = $app->share($app->extend('config', function ($config) {
            $nav = array(
                'id' => 'admin_sales_report',
                'name' => '売上集計',
                'has_child' => 'true',
                'icon' => 'icon-signal',
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
                    array(
                        'id' => 'admin_sales_report_member',
                        'url' => 'admin_sales_report_member',
                        'name' => '会員別集計',
                    ),
                ),
            );

            $config['nav'][] = $nav;

            return $config;
        }));
    }

    public function boot(BaseApplication $app)
    {
    }
}