<?php
/*
 * This file is part of the Sales Report plugin
 *
 * Copyright (C) 2016 LOCKON CO.,LTD. All Rights Reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\SalesReport\Tests\Web;

use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;

/**
 * Class SaleReportCommon.
 */
class SaleReportCommon extends AbstractAdminWebTestCase
{
    /**
     * createCustomerByNumber.
     *
     * @param int $number
     *
     * @return array
     */
    public function createCustomerByNumber($number = 5)
    {
        $arrCustomer = array();
        $current = new \DateTime();
        for ($i = 0; $i < $number; ++$i) {
            $email = 'customer0'.$i.'@mail.com';
            $age = ($i < 7) ? $i * 10 + 19 : $i * 10 - 19;
            $age = $current->modify("-$age years");
            $Customer = $this->createCustomer($email);
            $arrCustomer[] = $Customer->getId();
            $Customer->setBirth($age);
            $this->app['orm.em']->persist($Customer);
            $this->app['orm.em']->flush($Customer);
        }

        return $arrCustomer;
    }

    /**
     * createOrderByCustomer.
     *
     * @param int $number
     */
    public function createOrderByCustomer($number = 5)
    {
        $arrCustomer = $this->createCustomerByNumber($number);
        for ($i = 0; $i < count($arrCustomer); ++$i) {
            $Customer = $this->app['eccube.repository.customer']->find($arrCustomer[$i]);
            $Order = $this->createOrder($Customer);
            $Order->setOrderStatus($this->app['eccube.repository.order_status']->find($this->app['config']['order_new']));
            $this->app['orm.em']->persist($Order);
            $this->app['orm.em']->flush($Order);
        }
    }
}
