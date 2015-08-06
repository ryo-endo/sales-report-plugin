<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
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
        $form = $app['form.factory']
            ->createBuilder('sales_report')
            ->getForm();
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