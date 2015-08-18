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

namespace Plugin\SalesReport\Form\Type;

use \Symfony\Component\Form\AbstractType;
use \Symfony\Component\Form\Extension\Core\Type;
use \Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use \Symfony\Component\Validator\Constraints as Assert;

class SalesReportType extends AbstractType
{
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
            ))
            ->add('term_start', 'date', array(
                'label' => '期間集計(FROM)',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'empty_value' => array('year' => '----', 'month' => '--', 'day' => '--'),
            ))
            ->add('term_end', 'date', array(
                'label' => '期間集計(TO)',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'empty_value' => array('year' => '----', 'month' => '--', 'day' => '--'),
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
                )
            ))
            ->addEventListener(FormEvents::POST_SUBMIT, function ($event) {
                $form = $event->getForm();
                $data = $form->getData();
                if ($data['term_type'] === 'monthly' && empty($data['monthly'])) {
                    $form['monthly']->addError(new FormError('集計月を選択してください。'));
                } elseif ($data['term_type'] === 'term'
                    && (empty($data['term_start']) || empty($data['term_end']))) {
                    $form['term_start']->addError(new FormError('集計期間を正しく選択してください。'));
                }
            })
        ;
    }

    public function getName()
    {
        return 'sales_report';
    }

}
