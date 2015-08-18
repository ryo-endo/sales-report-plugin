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

namespace Plugin\SalesReport\Controller;

use Eccube\Application;
use Symfony\Component\HttpFoundation\Request;

class SalesReportController
{
    public function index(Application $app, Request $request)
    {
        return $this->response($app, $request);
    }

    public function term(Application $app, Request $request)
    {
        return $this->response($app, $request, 'term');
    }

    public function product(Application $app, Request $request)
    {
        return $this->response($app, $request, 'product');
    }

    public function age(Application $app, Request $request)
    {
        return $this->response($app, $request, 'age');
    }

    public function member(Application $app, Request $request)
    {
        return $this->response($app, $request, 'member');
    }

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
            'raw' => null
        );

        if (!is_null($reportType) && $form->isValid()) {
            $data = $app['eccube.plugin.service.sales_report']
                ->setReportType($reportType)
                ->setTerm($form->get('term_type')->getData(), $form->getData())
                ->getData();
        }

        $template = is_null($reportType) ? 'term' : $reportType;

        return $app->render(
            'SalesReport/Resource/template/' . $template . '.twig',
            array(
                'form' => $form->createView(),
                'graphData' => json_encode($data['graph']),
                'rawData' => $data['raw'],
                'type' => $reportType,
            )
        );
    }
}