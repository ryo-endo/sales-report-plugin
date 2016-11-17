<?php
/*
 * This file is part of the Sales Report plugin
 *
 * Copyright (C) 2016 LOCKON CO.,LTD. All Rights Reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\SalesReport\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class SalesReportType.
 */
class SalesReportType extends AbstractType
{
    /**
     * @var \Eccube\Application
     */
    private $app;

    /**
     * RelatedProductType constructor.
     *
     * @param \Eccube\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * buildForm Sale Report.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('term_type', 'hidden', array(
                'required' => false,
            ))
            ->add('monthly', 'date', array(
                'label' => '月度集計',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'empty_value' => array('year' => '----', 'month' => '--', 'day' => '--'),
                'data' => new \DateTime(),
            ))
            ->add('term_start', 'date', array(
                'label' => '期間集計(FROM)',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'empty_value' => array('year' => '----', 'month' => '--', 'day' => '--'),
                'data' => new \DateTime(),
            ))
            ->add('term_end', 'date', array(
                'label' => '期間集計(TO)',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'empty_value' => array('year' => '----', 'month' => '--', 'day' => '--'),
                'data' => new \DateTime(),
            ))
            ->add('unit', 'choice', array(
                'label' => '集計単位',
                'required' => false,
                'expanded' => true,
                'multiple' => false,
                'empty_value' => false,
                'choices' => array(
                    'byDay' => '日別',
                    'byMonth' => '月別',
                    'byWeekDay' => '曜日別',
                    'byHour' => '時間別',
                ),
                'constraints' => array(
                    new Assert\NotBlank(),
                ),
            ))
            ->addEventListener(FormEvents::POST_SUBMIT, function ($event) {
                $form = $event->getForm();
                $data = $form->getData();
                if ($data['term_type'] === 'monthly' && empty($data['monthly'])) {
                    $form['monthly']->addError(new FormError($this->app->trans('plugin.sales_report.type.montly.error')));
                } elseif ($data['term_type'] === 'term'
                    && (empty($data['term_start']) || empty($data['term_end']))) {
                    $form['term_start']->addError(new FormError($this->app->trans('plugin.sales_report.type.term_start.error')));
                }
            })
        ;
    }

    /**
     * get sale report form name.
     *
     * @return string
     */
    public function getName()
    {
        return 'sales_report';
    }
}
