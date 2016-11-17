<?php
/*
 * This file is part of the Related Product plugin
 *
 * Copyright (C) 2016 LOCKON CO.,LTD. All Rights Reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\SalesReport\Controller;

use Eccube\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class SalesReportController.
 */
class SalesReportController
{
    /**
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
    /**
     * CSVの出力.
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return StreamedResponse
     */
    public function export(Application $app, Request $request)
    {
        // タイムアウトを無効にする.
        set_time_limit(0);

        // sql loggerを無効にする.
        $em = $app['orm.em'];
        $em->getConfiguration()->setSQLLogger(null);

        $response = new StreamedResponse();
        $response->setCallback(function () use ($app, $request) {

            // CSV種別を元に初期化.
            $app['eccube.service.csv.export']->initCsvType(CsvType::CSV_TYPE_PRODUCT);

            // ヘッダ行の出力.
            $app['eccube.service.csv.export']->exportHeader();

            // 商品データ検索用のクエリビルダを取得.
            $qb = $app['eccube.service.csv.export']
                ->getProductQueryBuilder($request);

            // joinする場合はiterateが使えないため, select句をdistinctする.
            // http://qiita.com/suin/items/2b1e98105fa3ef89beb7
            // distinctのmysqlとpgsqlの挙動をあわせる.
            // http://uedatakeshi.blogspot.jp/2010/04/distinct-oeder-by-postgresmysql.html
            $qb->resetDQLPart('select')
                ->resetDQLPart('orderBy')
                ->select('p')
                ->orderBy('p.update_date', 'DESC')
                ->distinct();

            // データ行の出力.
            $app['eccube.service.csv.export']->setExportQueryBuilder($qb);
            $app['eccube.service.csv.export']->exportData(function ($entity, $csvService) {
                $Csvs = $csvService->getCsvs();

                /** @var $Product \Eccube\Entity\Product */
                $Product = $entity;

                /* @var $Product \Eccube\Entity\ProductClass[] */
                $ProductClassess = $Product->getProductClasses();

                foreach ($ProductClassess as $ProductClass) {
                    $row = array();

                    // CSV出力項目と合致するデータを取得.
                    foreach ($Csvs as $Csv) {
                        // 商品データを検索.
                        $data = $csvService->getData($Csv, $Product);
                        if (is_null($data)) {
                            // 商品規格情報を検索.
                            $data = $csvService->getData($Csv, $ProductClass);
                        }
                        $row[] = $data;
                    }

                    //$row[] = number_format(memory_get_usage(true));
                    // 出力.
                    $csvService->fputcsv($row);
                }
            });
        });

        $now = new \DateTime();
        $filename = 'product_'.$now->format('YmdHis').'.csv';
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);
        $response->send();

        return $response;
    }
}
