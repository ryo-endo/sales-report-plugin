<?php
/*
 * This file is part of the Sales Report plugin
 *
 * Copyright (C) 2016 LOCKON CO.,LTD. All Rights Reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\SalesReport\Controller;

use Eccube\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class SalesReportController.
 */
class SalesReportController
{
    /**
     * redirect by report type. default is term.
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index(Application $app, Request $request)
    {
        return $this->response($app, $request);
    }

    /**
     * 期間別集計.
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function term(Application $app, Request $request)
    {
        return $this->response($app, $request, 'term');
    }

    /**
     * 商品別集計.
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function product(Application $app, Request $request)
    {
        return $this->response($app, $request, 'product');
    }

    /**
     * 年代別集計.
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function age(Application $app, Request $request)
    {
        return $this->response($app, $request, 'age');
    }

    /**
     * direct by report type.
     *
     * @param Application $app
     * @param Request     $request
     * @param null        $reportType
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function response(Application $app, Request $request, $reportType = null)
    {
        /* @var $form \Symfony\Component\Form\Form */
        $builder = $app['form.factory']
            ->createBuilder('sales_report');
        if (!is_null($reportType) && $reportType !== 'term') {
            $builder->remove('unit');
        }
        $form = $builder->getForm();
        $form->handleRequest($request);

        $data = array(
            'graph' => null,
            'raw' => null,
        );

        if (!is_null($reportType) && $form->isValid()) {
            $data = $app['eccube.plugin.service.sales_report']
                ->setReportType($reportType)
                ->setTerm($form->get('term_type')->getData(), $form->getData())
                ->getData();
        }

        $template = is_null($reportType) ? 'term' : $reportType;
        log_info('SalesReport Plugin : render ', array('template' => $template));

        return $app->render(
            'SalesReport/Resource/template/'.$template.'.twig',
            array(
                'form' => $form->createView(),
                'graphData' => json_encode($data['graph']),
                'rawData' => $data['raw'],
                'type' => $reportType,
            )
        );
    }
}
