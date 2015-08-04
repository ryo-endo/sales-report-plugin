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
namespace Plugin\SalesReport\Service;

use Eccube\Application;

class SalesReportService
{
    private $app;

    private $reportType;

    private $termStart;

    private $termEnd;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function setReportType($reportType)
    {
        $this->reportType = $reportType;

        return $this;
    }

    public function setTerm($termType, $request)
    {
        // termStart <= X < termEnd となるように整形する
        if ($termType === 'monthly') {
            $date = $request['monthly'];
            $start = $date->format("Y-m-01 00:00:00");
            $end = $date
                ->modify('+ 1 month')
                ->format("Y-m-01 00:00:00");

            $this
                ->setTermStart($start)
                ->setTermEnd($end);
        } else {
            $end = $request['term_end']
                ->modify('+ 1 day')
                ->format("Y-m-d 00:00:00");

            $this
                ->setTermStart($request['term_start'])
                ->setTermEnd($end);
        }

        return $this;
    }

    private function setTermStart($term)
    {
        $this->termStart = $term;

        return $this;
    }

    private function setTermEnd($term)
    {
        $this->termEnd = $term;

        return $this;
    }

    public function getData()
    {
        $app = $this->app;
        $dql = 'SELECT
                  o.order_date,
                  SUM(o.payment_total) AS order_amount,
                  COUNT(o) AS order_count
                FROM
                  Eccube\Entity\Order o
                WHERE
                    o.del_flg = 0
                    AND o.order_date >= :start
                    AND o.order_date <= :end
                    AND o.OrderStatus NOT IN (:excludes)
                GROUP BY
                  order_day';

        $excludes = array(
            $app['config']['order_processing'],
            $app['config']['order_cancel'],
            $app['config']['order_pending'],
        );

        $q = $app['orm.em']
            ->createQuery($dql)
            ->setParameter(':excludes', $excludes)
            ->setParameter(':start', $this->termStart)
            ->setParameter(':end', $this->termEnd);

        $result = array();
        try {
            $result = $q->getSingleResult();
        } catch (NoResultException $e) {
        }
        return $result;
    }

}