<?php
/**
 * Plugin for shopware shops. Enables export and import of shopware configuration data
 * from database to files. Primary purpose is to make shopware config manageable
 * to version control systems like git. Also useful for deployment and backup purposes.
 *
 * Copyright (C) 2015-2018 MND Next GmbH <info@mndnext.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace ShopwarePlugins\MndConfigExportImport\Commands;

use ShopwarePlugins\MndConfigExportImport\Components\MndConfigExporter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Shopware\Models\Mail\Mail;
use Shopware\Models\Mail\Template;
use Shopware\Models\Mail\Attachment;
use ShopwarePlugins\MndConfigExportImport\Utils\MndConfigIO;
use ShopwarePlugins\MndConfigExportImport\Components\XMLExporter;

/**
 * Class ExportMailCommand
 * @package Shopware\Plugin\MndConfigExportImport\Commands
 */
class ExportMailCommand extends MndExportCommand
{
    /*
    * Dieser Text wird am Anfang des Exports als Kommentar angezeigt
    */
    private $exportText = "
        Um ein neues Bild hinzufügen muss das 'attachment' Feld erweitert werden. MediaId muss 'null' sein.\n
        Der Anhang muss zusätzlich in den entsprechenden Order abgelegt werden.
    ";

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('mnd:sw'.$this->getCommandName().':export')
            ->setDescription('Export Mail Templates to directory ' . $this->getDefaultPath() . ' or custom target via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "mail";
    }

    /**
     * @param MndConfigIO $io
     * @param XMLExporter $exporter
     * @param Mail $email
     */
    private function writeAttachments($io, $exporter, $email) {
        /* @var $mediaService \Shopware\Bundle\MediaBundle\MediaService */
        $mediaService = Shopware()->Container()->get('shopware_media.media_service');


        $mailRepository = Shopware()->Models()->getRepository('Shopware\Models\Mail\Mail');
        $attachmentRepo = Shopware()->Models()->getRepository('Shopware\Models\Mail\Attachment');
        $dbMailElement = $mailRepository->findOneBy(array(
            'name' => $email->getName()
        ));

        /** @var \Shopware\Models\Mail\Attachment[] $attachments */
        $attachments = $attachmentRepo->findBy(array(
            'mail' => $dbMailElement
        ));

        if (!empty($attachments)) {
            $exporter->startList('Attachments');
            foreach ($attachments as $attachment) {
                $media = $attachment->getMedia();
                $file = $mediaService->encode($media->getPath());
                $sourceFile = Shopware()->DocPath() . $file;
                $destFile = 'attachments/' . ($attachment->getShopId()?$attachment->getShopId().'/':'') . $attachment->getFileName();
                $mediaPath = $media->getPath();
                $i = 0;
                while ($io->exists($destFile)) {
                    $i++;
                    $base = pathinfo($attachment->getFileName(), PATHINFO_FILENAME);
                    $extension = pathinfo($attachment->getFileName(), PATHINFO_EXTENSION);
                    $destFile = 'attachments/' . $attachment->getShopId() . "/$base($i).$extension";
                }
                $exporter->newElement('Attachment');
                $exporter->addAttribute('shopId', $attachment->getShopId());
                $exporter->addAttribute('name', $attachment->getName());
                $exporter->addAttribute('albumName', $media->getAlbum()->getName());
                $exporter->addAttribute('file', $destFile);
                $exporter->addAttribute('path', $mediaPath);

                // copy attachment file to
                $this->copy($io, $sourceFile, $destFile);
            }
            $exporter->finishList();
        }

    }

    /**
     * @param MndConfigIO $io
     * @param XMLExporter $exporter
     * @param Mail $email
     */
    private function writeTranslations($io, $exporter, $email) {
        // get available translations
        $sql = "
                  SELECT *
                  FROM s_core_translations
                  WHERE
                    objecttype = :objecttype
                  AND
                    objectkey = :objectkey
            ";
        $dbTranslations = Shopware()->Db()->fetchAll($sql,array(
            "objecttype" => 'config_mails',
            "objectkey" => $email->getId()
        ));

        $translationComponent = new \Shopware_Components_Translation();
        foreach ($dbTranslations as $translation) {
            $content = $translationComponent->read($translation['objectlanguage'], $translation['objecttype'], $translation['objectkey']);
            $shopId = $translation['objectlanguage'];
            $fileHtml = 'content.' . $shopId . '.html.tpl';
            $fileText = 'content.' . $shopId . '.txt.tpl';

            $exporter->newElement('Template');
            $exporter->addAttribute('shopId', (int) $shopId);
            if ($content['contentHtml']) {
                $exporter->addAttribute('html', $fileHtml);
                $this->write($io, $fileHtml, $content['contentHtml']);
            }
            if ($content['content']) {
                $exporter->addAttribute('text', $fileText);
                $this->write($io, $fileText, $content['content']);
            }
            $exporter->add('FromMail', $content['fromMail']);
            $exporter->add('FromName', $content['fromName']);
            $exporter->add('Subject', $content['subject']);

        }
    }

    /**
     * @param MndConfigIO $io
     * @param Mail $email
     */
    private function writeEmail($io, $email) {
        $exporter = new MndConfigExporter();
        $io->setSubPath($email->getName());
        $shopId = 1;

        $exporter->newElement('Email');
        $exporter->addAttribute('name', $email->getName());
        $exporter->addAttribute('isHtml', (int) $email->isHtml());
        $exporter->startList('Templates');
            // default shop
            $exporter->newElement('Template');
            $exporter->addAttribute('shopId', (int) $shopId);
            $contentHtml = $email->getContentHtml();
            if ($contentHtml) {
                $fileHtml = 'content.' . $shopId . '.html.tpl';
                $exporter->addAttribute('html', $fileHtml);
                $this->write($io, $fileHtml, $contentHtml);
            }
            $content = $email->getContent();
            if ($content) {
                $fileText = 'content.' . $shopId . '.txt.tpl';
                $exporter->addAttribute('text', $fileText);
                $this->write($io, $fileText, $content);
            }
            $exporter->addProp($email, 'FromMail');
            $exporter->addProp($email, 'FromName');
            $exporter->addProp($email, 'Subject');

            // translations
            $this->writeTranslations($io, $exporter, $email);
        $exporter->finishList();
        $this->writeAttachments($io, $exporter, $email);

        $this->write($io, self::CONFIG_FILE, $exporter->get(), true);
        $io->setSubPath(''); // remove subpath
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        try {
            $io = new MndConfigIO($this->getDefaultPath(), $input->getOptions());
            $io->setModulePath($this->getCommandName());
            $mailRepository = Shopware()->Models()->getRepository('Shopware\Models\Mail\Mail');
            foreach ($mailRepository->findAll() as $email) {
                $this->writeEmail($io, $email);
            }
        } catch (\Exception $e) {
            $this->printError($e->getMessage());
        }

    }
}