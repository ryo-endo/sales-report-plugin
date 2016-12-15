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
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class SalesReportController.
 */
class SalesReportController
{
    /**
     * @var array
     */
    private $productCsvHeader = array('商品コード', '商品名', '購入件数(件)', '数量(個)', '単価(円)', '金額(円)');

    /**
     * @var array
     */
    private $termCsvHeader = array('期間', '購入件数', '男性', '女性', '男性(会員)', '男性(非会員)', '女性(会員)', '女性(非会員)', '購入合計(円)', '購入平均(円)');

    /**
     * @var array
     */
    private $ageCsvHeader = array('年代', '購入件数(件)', '購入合計(円)', '購入平均(円)');

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
     * 商品CSVの出力.
     *
     * @param Application $app
     * @param Request     $request
     * @param string      $type
     *
     * @return StreamedResponse
     */
    public function export(Application $app, Request $request, $type)
    {
        set_time_limit(0);
        $response = new StreamedResponse();
        $session = $request->getSession();
        $filename = '';
        if ($session->has('eccube.admin.plugin.sales_report.export')) {
            $searchData = $session->get('eccube.admin.plugin.sales_report.export');
        } else {
            $searchData = array();
        }

        $data = array(
            'graph' => null,
            'raw' => null,
        );

        // Query data from database
        if ($searchData) {
            $searchData['term_end'] = $searchData['term_end']->modify('- 1 day');
            $data = $app['salesreport.service.sales_report']
                ->setReportType($type)
                ->setTerm($searchData['term_type'], $searchData)
                ->getData();
        }

        $response->setCallback(function () use ($data, $app, $request, $type) {
            //export data by type
            switch ($type) {
                case 'term':
                    $this->exportTermCsv($data['raw'], $app['config']['csv_export_separator'], $app['config']['csv_export_encoding']);
                    break;
                case 'product':
                    $this->exportProductCsv($data['raw'], $app['config']['csv_export_separator'], $app['config']['csv_export_encoding']);
                    break;
                case 'age':
                    $this->exportAgeCsv($data['raw'], $app['config']['csv_export_separator'], $app['config']['csv_export_encoding']);
                    break;
            }
        });

        //set filename by type
        $now = new \DateTime();
        switch ($type) {
            case 'term':
                $filename = '期間別集計_'.$now->format('YmdHis').'.csv';
                break;
            case 'product':
                $filename = '商品別集計_'.$now->format('YmdHis').'.csv';
                break;
            case 'age':
                $filename = '年代別集計_'.$now->format('YmdHis').'.csv';
                break;
        }

        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);
        $response->send();
        log_info('商品CSV出力ファイル名', array($filename));

        return $response;
    }

    /**
     * direct by report type(default term).
     *
     * @param Application $app
     * @param Request     $request
     * @param null        $reportType
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function response(Application $app, Request $request, $reportType = null)
    {
        $builder = $app['form.factory']
            ->createBuilder('sales_report');
        if (!is_null($reportType) && $reportType !== 'term') {
            $builder->remove('unit');
        }
        /* @var $form \Symfony\Component\Form\Form */
        $form = $builder->getForm();
        $form->handleRequest($request);

        $data = array(
            'graph' => null,
            'raw' => null,
        );

        if (!is_null($reportType) && $form->isValid()) {
            $session = $request->getSession();
            $searchData = $form->getData();
            $searchData['term_type'] = $form->get('term_type')->getData();
            $session->set('eccube.admin.plugin.sales_report.export', $searchData);
            $data = $app['salesreport.service.sales_report']
                ->setReportType($reportType)
                ->setTerm($form->get('term_type')->getData(), $searchData)
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

    /**
     * get product report csv.
     *
     * @param array  $rows
     * @param string $separator
     * @param string $encoding
     */
    private function exportProductCsv($rows, $separator, $encoding)
    {
        try {
            $handle = fopen('php://output', 'w+');
            $headers = $this->productCsvHeader;
            $headerRow = array();
            //convert header to encoding
            foreach ($headers as $header) {
                $headerRow[] = mb_convert_encoding($header, $encoding, 'UTF-8');
            }
            fputcsv($handle, $headerRow, $separator);
            //convert data to encoding
            foreach ($rows as $id => $row) {
                $code = mb_convert_encoding($row['OrderDetail']->getProductCode(), $encoding, 'UTF-8');
                $name = mb_convert_encoding($row['OrderDetail']->getProductName(), $encoding, 'UTF-8');
                fputcsv($handle, array($code, $name, $row['time'], $row['quantity'], $row['price'], $row['total']), $separator);
            }
            fclose($handle);
        } catch (\Exception $e) {
            log_info('CSV product export exception', array($e->getMessage()));
        }
    }

    /**
     * get term report csv.
     *
     * @param array  $rows
     * @param string $separator
     * @param string $encoding
     */
    private function exportTermCsv($rows, $separator, $encoding)
    {
        try {
            $handle = fopen('php://output', 'w+');
            $headers = $this->termCsvHeader;
            $headerRow = array();
            //convert header to encoding
            foreach ($headers as $header) {
                $headerRow[] = mb_convert_encoding($header, $encoding, 'UTF-8');
            }
            fputcsv($handle, $headerRow, $separator);
            foreach ($rows as $date => $row) {
                if ($row['time'] > 0) {
                    $money = round($row['price'] / $row['time']);
                } else {
                    $money = 0;
                }
                fputcsv($handle, array($date, $row['time'], $row['male'], $row['female'], $row['other'], $row['member_male'], $row['nonmember_male'], $row['member_female'], $row['nonmember_female'], $row['price'], $money), $separator);
            }
            fclose($handle);
        } catch (\Exception $e) {
            log_info('CSV term export exception', array($e->getMessage()));
        }
    }

    /**
     * get age report csv.
     *
     * @param array  $rows
     * @param string $separator
     * @param string $encoding
     */
    private function exportAgeCsv($rows, $separator, $encoding)
    {
        try {
            $handle = fopen('php://output', 'w+');
            $headers = $this->ageCsvHeader;
            $headerRow = array();
            //convert header to encoding
            foreach ($headers as $header) {
                $headerRow[] = mb_convert_encoding($header, $encoding, 'UTF-8');
            }
            fputcsv($handle, $headerRow, $separator);
            foreach ($rows as $age => $row) {
                if ($row['time'] > 0) {
                    $money = round($row['total'] / $row['time']);
                } else {
                    $money = 0;
                }
                $age = mb_convert_encoding($age, $encoding, 'UTF-8');
                fputcsv($handle, array($age, $row['time'], $row['total'], $money), $separator);
            }
            fclose($handle);
        } catch (\Exception $e) {
            log_info('CSV age export exception', array($e->getMessage()));
        }
    }
}
