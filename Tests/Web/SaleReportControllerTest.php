<?php
/*
 * This file is part of the Related Product plugin
 *
 * Copyright (C) 2016 LOCKON CO.,LTD. All Rights Reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\SalesReport\Tests\Web;

/**
 * Class SaleReportControllerTest.
 */
class SaleReportControllerTest extends SaleReportCommon
{
    /**
     * testRouting.
     *
     * @param string $type
     * @param string $expected
     * @dataProvider dataRoutingProvider
     */
    public function testRouting($type, $expected)
    {
        $crawler = $this->client->request('GET', $this->app->url('admin_sales_report'.$type));
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertContains($expected, $crawler->html());
    }

    /**
     * dataRoutingProvider.
     *
     * @return array
     */
    public function dataRoutingProvider()
    {
        return array(
            array('', '期間別集計'),
            array('_term', '期間別集計'),
            array('_product', '商品別集計'),
            array('_age', '年代別集計'),
        );
    }

    /**
     * testReportByMonth.
     *
     * @param string $type
     * @param string $termType
     * @param string $expected
     * @param array  $unit
     * @dataProvider dataReportProvider
     */
    public function testReportByMonth($type, $termType, $expected, $unit = null)
    {
        $this->createOrderByCustomer(1);
        $current = new \DateTime();
        $arrSearch = array(
            'term_type' => $termType,
            '_token' => 'dummy',
        );
        if ($type == '' || $type == '_term') {
            $arrSearch['unit'] = $unit;
        }

        if ($termType == 'monthly') {
            $arrSearch['monthly'] = $current->format('Y-m-d');
        } else {
            $arrSearch['term_start'] = $current->modify('-5 days')->format('Y-m-d');
            $arrSearch['term_end'] = $current->modify('+5 days')->format('Y-m-d');
        }
        $crawler = $this->client->request('POST', $this->app->url('admin_sales_report'.$type), array('sales_report' => $arrSearch));

        $this->assertContains($expected, $crawler->html());
    }

    /**
     * dataReportProvider.
     *
     * @return array
     */
    public function dataReportProvider()
    {
        return array(
            array('', 'monthly', 'byDay', '購入平均'),
            array('', 'monthly', 'byMonth', '購入平均'),
            array('', 'monthly', 'byWeekDay', '購入平均'),
            array('', 'monthly', 'byHour', '購入平均'),
            array('', 'term', 'byDay', '購入平均'),
            array('', 'term', 'byMonth', '購入平均'),
            array('', 'term', 'byWeekDay', '購入平均'),
            array('', 'term', 'byHour', '購入平均'),
            array('_term', 'monthly', 'byDay', '購入平均'),
            array('_term', 'monthly', 'byMonth', '購入平均'),
            array('_term', 'monthly', 'byWeekDay', '購入平均'),
            array('_term', 'monthly', 'byHour', '購入平均'),
            array('_term', 'term', 'byDay', '購入平均'),
            array('_term', 'term', 'byMonth', '購入平均'),
            array('_term', 'term', 'byWeekDay', '購入平均'),
            array('_term', 'term', 'byHour', '購入平均'),
            array('_product', 'monthly', null, '商品名'),
            array('_product', 'term', null, '商品名'),
            array('_age', 'monthly', null, '年齢'),
            array('_age', 'term', null, '年齢'),
        );
    }
}
