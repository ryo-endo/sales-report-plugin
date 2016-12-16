<?php
/*
 * This file is part of the Sales Report plugin
 *
 * Copyright (C) 2016 LOCKON CO.,LTD. All Rights Reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\SalesReport\Service;

use DateTime;
use Doctrine\ORM\EntityManager;
use Eccube\Application;
use Eccube\Util\EntityUtil;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\NoResultException;

/**
 * Class SalesReportService.
 */
class SalesReportService
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @var string
     */
    private $reportType;

    /**
     * @var \DateTime
     */
    private $termStart;

    /**
     * @var \DateTime
     */
    private $termEnd;

    /**
     * @var string
     */
    private $unit;

    /**
     * @var int
     */
    const MALE = 1;

    /**
     * @var int
     */
    const FEMALE = 2;

    /**
     * SalesReportService constructor.
     *
     * @param Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * setReportType.
     *
     * @param string $reportType
     *
     * @return SalesReportService
     */
    public function setReportType($reportType)
    {
        $this->reportType = $reportType;

        return $this;
    }

    /**
     * set term from , to.
     *
     * @param string  $termType
     * @param Request $request
     *
     * @return SalesReportService
     */
    public function setTerm($termType, $request)
    {
        if ($termType === 'monthly') {
            // 月度集計
            $year = $request['monthly_year'];
            $month = $request['monthly_month'];

            $date = new DateTime();
            $date->setDate($year, $month, 1)->setTime(0, 0, 0);

            $start = $date->format('Y-m-d G:i:s');
            $end = $date->modify('+ 1 month')->format('Y-m-d G:i:s');

            $this
                ->setTermStart($start)
                ->setTermEnd($end);
        } else {
            // 期間集計
            $start = $request['term_start']
                ->format('Y-m-d 00:00:00');
            $end = $request['term_end']
                ->modify('+ 1 day')
                ->format('Y-m-d 00:00:00');

            $this->setTermStart($start);
            $this->setTermEnd($end);
        }

        // 集計単位を設定
        if (isset($request['unit'])) {
            $this->unit = $request['unit'];
        }

        return $this;
    }

    /**
     * query and get order data.
     *
     * @return array
     */
    public function getData()
    {
        $app = $this->app;

        $excludes = array(
            $app['config']['order_processing'],
            $app['config']['order_cancel'],
            $app['config']['order_pending'],
        );

        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $qb = $app['orm.em']->createQueryBuilder();
        $qb
            ->select('o')
            ->from('Eccube\Entity\Order', 'o')
            ->andWhere('o.del_flg = 0')
            ->andWhere('o.order_date >= :start')
            ->andWhere('o.order_date <= :end')
            ->andWhere('o.OrderStatus NOT IN (:excludes)')
            ->setParameter(':excludes', $excludes)
            ->setParameter(':start', $this->termStart)
            ->setParameter(':end', $this->termEnd);

        log_info('SalesReport Plugin : search parameters ', array('From' => $this->termStart, 'To' => $this->termEnd));
        $result = array();
        try {
            $result = $qb->getQuery()->getResult();
        } catch (NoResultException $e) {
            log_info('SalesReport Plugin : Exception '.$e->getMessage());
        }

        return $this->convert($result);
    }

    /**
     * setTermStart.
     *
     * @param \DateTime $term
     *
     * @return SalesReportService
     */
    private function setTermStart($term)
    {
        $this->termStart = $term;

        return $this;
    }

    /**
     * setTermEnd.
     *
     * @param \DateTime $term
     *
     * @return SalesReportService
     */
    private function setTermEnd($term)
    {
        $this->termEnd = $term;

        return $this;
    }

    /**
     * convert to graph data by report type.
     *
     * @param array $data
     *
     * @return array
     */
    private function convert($data)
    {
        $result = array();
        switch ($this->reportType) {
            case 'term':
                $result = $this->convertByTerm($data);
                break;
            case 'product':
                $result = $this->convertByProduct($data);
                break;
            case 'age':
                $result = $this->convertByAge($data);
                break;
        }

        return $result;
    }

    /**
     * format unit date time.
     *
     * @return array
     */
    private function formatUnit()
    {
        $unit = array(
            'byDay' => 'Y-m-d',
            'byMonth' => 'Y-m',
            'byWeekDay' => 'D',
            'byHour' => 'H',
        );

        return $unit[$this->unit];
    }

    /**
     * sort array by value.
     *
     * @param string $field
     * @param array  $array
     * @param string $direction
     *
     * @return array
     */
    private function sortBy($field, &$array, $direction = 'desc')
    {
        usort($array, create_function('$a, $b', '
            $a = $a["'.$field.'"];
            $b = $b["'.$field.'"];
            if ($a == $b) {
                return 0;
            }
   
            return ($a '.($direction == 'desc' ? '>' : '<').' $b) ? -1 : 1;
	    '));

        return $array;
    }

    /**
     * get background color.
     *
     * @param int $index
     *
     * @return array
     */
    private function getColor($index)
    {
        $map = array(
            '#F2594B',
            '#D17A45',
            '#FFAB48',
            '#FFE7AD',
            '#FFD393',
            '#9C9B7A',
            '#A7C9AE',
            '#63A69F',
            '#3F5765',
            '#685C79',
        );
        $colorIndex = $index % count($map);

        return $map[$colorIndex];
    }

    /**
     * period sale report.
     *
     * @param array $data
     *
     * @return array
     */
    private function convertByTerm($data)
    {
        $start = new \DateTime($this->termStart);
        $end = new \DateTime($this->termEnd);
        $raw = array();
        $price = array();
        $orderNumber = 0;
        $format = $this->formatUnit();

        // Sort date in week
        if ($this->unit == 'byWeekDay') {
            $raw = array('Sun' => '', 'Mon' => '', 'Tue' => '', 'Wed' => '', 'Thu' => '', 'Fri' => '', 'Sat' => '');
            $price = $raw;
        }

        for ($term = $start; $term < $end; $term = $term->modify('+ 1 Hour')) {
            $date = $term->format($format);
            $raw[$date] = array(
                'price' => 0,
                'time' => 0,
                'male' => 0,
                'female' => 0,
                'other' => 0,
                'member_male' => 0,
                'nonmember_male' => 0,
                'member_female' => 0,
                'nonmember_female' => 0,
            );
            $price[$date] = 0;
        }

        /* @var $entityManager EntityManager */
        $entityManager = $this->app['orm.em'];
        $sql = 'Select o.customer_id From dtb_order o Where o.order_id = :order_id';
        $stmt = $entityManager->getConnection()->prepare($sql);
        foreach ($data as $Order) {
            /* @var $Order \Eccube\Entity\Order */
            $orderDate = $Order
                ->getOrderDate()
                ->format($format);
            $price[$orderDate] += $Order->getPaymentTotal();
            $raw[$orderDate]['price'] += $Order->getPaymentTotal();
            ++$raw[$orderDate]['time'];

            // Get sex
            $Sex = $Order->getSex();
            $sex = 0;
            if (EntityUtil::isNotEmpty($Sex)) {
                $sex = $Order->getSex()->getId();
            } else {
                $raw[$orderDate]['other'] += 1;
            }
            $raw[$orderDate]['male'] += ($sex == self::MALE);
            $raw[$orderDate]['female'] += ($sex == self::FEMALE);

            // Get customer id
            $params['order_id'] = $Order->getId();
            $stmt->execute($params);
            $customerId = $stmt->fetch(\PDO::FETCH_COLUMN);
            if ($customerId) {
                $raw[$orderDate]['member_male'] += ($sex == self::MALE);
                $raw[$orderDate]['member_female'] += ($sex == self::FEMALE);
            } else {
                $raw[$orderDate]['nonmember_male'] += ($sex == self::MALE);
                $raw[$orderDate]['nonmember_female'] += ($sex == self::FEMALE);
            }

            ++$orderNumber;
        }

        log_info('SalesReport Plugin : term report ', array('result count' => $orderNumber));
        // Return null and not display in screen
        if ($orderNumber == 0) {
            return array(
                'raw' => null,
                'graph' => null,
            );
        }

        $graph = array(
            'labels' => array_keys($price),
            'datasets' => array(
                'label' => '購入合計',
                'data' => array_values($price),
                'lineTension' => 0.1,
                'backgroundColor' => 'rgba(75,192,192,0.4)',
                'borderColor' => 'rgba(75,192,192,1)',
                'borderCapStyle' => 'butt',
                'borderDash' => array(),
                'borderDashOffset' => 0.0,
                'borderJoinStyle' => 'miter',
                'pointBorderColor' => 'rgba(75,192,192,1)',
                'pointBackgroundColor' => '#fff',
                'pointBorderWidth' => 1,
                'pointHoverRadius' => 5,
                'pointHoverBackgroundColor' => 'rgba(75,192,192,1)',
                'pointHoverBorderColor' => 'rgba(220,220,220,1)',
                'pointHoverBorderWidth' => 2,
                'pointRadius' => 1,
                'pointHitRadius' => 10,
                'spanGaps' => false,
                'borderWidth' => 1,
            ),
        );

        return array(
            'raw' => $raw,
            'graph' => $graph,
        );
    }

    /**
     * product sale report.
     *
     * @param array $data
     *
     * @return array
     */
    private function convertByProduct($data)
    {
        $label = array();
        $graphData = array();
        $backgroundColor = array();
        $products = array();
        /* @var $entityManager EntityManager */
        $entityManager = $this->app['orm.em'];
        $sql = 'Select od.product_class_id From dtb_order_detail od Where od.order_detail_id = :order_detail_id';
        $stmt = $entityManager->getConnection()->prepare($sql);
        foreach ($data as $Order) {
            /* @var $Order \Eccube\Entity\Order */
            $OrderDetails = $Order->getOrderDetails();
            foreach ($OrderDetails as $OrderDetail) {
                // Get product class id
                $params['order_detail_id'] = $OrderDetail->getId();
                $stmt->execute($params);
                $productClassId = $stmt->fetch(\PDO::FETCH_COLUMN);
                if ($productClassId) {
                    if (!array_key_exists($productClassId, $products)) {
                        $products[$productClassId] = array(
                            'OrderDetail' => $OrderDetail,
                            'total' => 0,
                            'quantity' => 0,
                            'price' => 0,
                            'time' => 0,
                        );
                    }
                    $products[$productClassId]['quantity'] += $OrderDetail->getQuantity();
                    $products[$productClassId]['price'] = $OrderDetail->getPriceIncTax();
                    ++$products[$productClassId]['time'];
                }
            }
        }
        //set total
        foreach ($products as $key => $product) {
            $total = $product['quantity'] * $product['price'];
            $products[$key]['total'] = $total;
        }
        //sort by total money
        $count = 0;
        $maxDisplayCount = $this->app['config']['SalesReport']['const']['product_maximum_display'];
        $products = $this->sortBy('total', $products);
        log_info('SalesReport Plugin : product report ', array('result count' => count($products)));
        foreach ($products as $key => $product) {
            $backgroundColor[$count] = $this->getColor($count);

            $label[$count] = $product['OrderDetail']->getProductName().' ';
            $label[$count] .= $product['OrderDetail']->getClassCategoryName1().' ';
            $label[$count] .= $product['OrderDetail']->getClassCategoryName2();
            $graphData[$count] = $product['total'];
            ++$count;

            if ($maxDisplayCount <= $count) {
                break;
            }
        }

        $result = array(
            'labels' => $label,
            'datasets' => array(
                'data' => $graphData,
                'backgroundColor' => $backgroundColor,
                'borderWidth' => 0,
            ),
        );

        //return null and not display in screen
        if ($count == 0) {
            return array(
                'raw' => null,
                'graph' => null,
            );
        }

        return array(
            'raw' => $products,
            'graph' => $result,
        );
    }

    /**
     * Age sale report.
     *
     * @param array $data
     *
     * @return array
     */
    private function convertByAge($data)
    {
        $raw = array();
        $result = array();
        $backgroundColor = array();
        $orderNumber = 0;
        foreach ($data as $Order) {
            $age = '未回答';
            /* @var $Order \Eccube\Entity\Order */
            $birth = $Order->getBirth();
            $orderDate = $Order->getOrderDate();
            if ($birth) {
                $orderDate = ($orderDate) ? $orderDate : new \DateTime();
                $age = (floor($birth->diff($orderDate)->y / 10) * 10).'代';
            }
            if (!array_key_exists($age, $result)) {
                $result[$age] = 0;
                $raw[$age] = array(
                    'total' => 0,
                    'time' => 0,
                );
            }
            $result[$age] += $Order->getPaymentTotal();
            $raw[$age]['total'] += $Order->getPaymentTotal();
            ++$raw[$age]['time'];
            $backgroundColor[$orderNumber] = $this->getColor($orderNumber);
            ++$orderNumber;
        }
        // Sort by age ASC.
        ksort($result);
        ksort($raw);
        log_info('SalesReport Plugin : age report ', array('result count' => count($raw)));
        // Return null and not display in screen
        if (count($raw) == 0) {
            return array(
                'raw' => null,
                'graph' => null,
            );
        }

        $graph = array(
            'labels' => array_keys($result),
            'datasets' => array(
                'label' => '購入合計',
                'backgroundColor' => $backgroundColor,
                'borderColor' => $backgroundColor,
                'borderWidth' => 0,
                'data' => array_values($result),
            ),
        );

        return array(
            'raw' => $raw,
            'graph' => $graph,
        );
    }
}
