<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Eccube\Entity\Master\CsvType;
use Eccube\Application;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20161114110252 extends AbstractMigration
{

    const NAME = 'mtb_csv_type';

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        if (!$schema->hasTable(self::NAME)) {
            return true;
        }

        $app = Application::getInstance();
        $em = $app["orm.em"];

        $CsvType = new CsvType();
        $CsvType->setId(3);
        $CsvType->setName('受注CSV');
        $CsvType->setRank(1);
        $em->persist($CsvType);

        $CsvType = new CsvType();
        $CsvType->setId(4);
        $CsvType->setName('配送CSV');
        $CsvType->setRank(2);
        $em->persist($CsvType);

        $CsvType = new CsvType();
        $CsvType->setId(5);
        $CsvType->setName('カテゴリCSV');
        $CsvType->setRank(5);
        $em->persist($CsvType);

        $em->flush();
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
    }
}
