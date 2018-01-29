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

use Shopware\Commands\ShopwareCommand;
use Shopware\Models\Mail\Attachment;
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigImporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLImporter;
use ShopwarePlugins\MndConfigExportImport\Exception\MismatchedVersionException;
use ShopwarePlugins\MndConfigExportImport\Exception\WrongRootException;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class ImportMailCommand
 * @package Shopware\Plugin\MndConfigExportImport\Commands
 */
class ImportMailCommand extends MndImportCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription('Import Mail Templates from directory ' . $this->getDefaultPath() . ' or custom source via -p');

        $this->addOption(
            'overwrite-attachments',
            null,
            InputOption::VALUE_NONE,
            'Force overwriting existing attachments in MediaService. WARNING: This cannot be undo!');

        $this->addOption(
            'ignore-attachments',
            null,
            InputOption::VALUE_NONE,
            'Ignore attachment import');

    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "mail";
    }

    /**
     * @param XMLImporter $importer
     * @param \Shopware\Models\Mail\Mail $dbEmail
     * @param bool $overwriteAttachments
     */
    private function importAttachments($importer, $dbEmail, $overwriteAttachments) {
        $attachmentRepo = Shopware()->Models()->getRepository('Shopware\Models\Mail\Attachment');
        $mediaRepo = Shopware()->Models()->getRepository('Shopware\Models\Media\Media');
        $thumbnailManager = Shopware()->Container()->get('thumbnail_manager');
        $albumRepo = Shopware()->Models()->getRepository('Shopware\Models\Media\Album');
        $mediaResource = \Shopware\Components\Api\Manager::getResource('Media');
        /* @var $mediaService \Shopware\Bundle\MediaBundle\MediaService */
        $mediaService = Shopware()->Container()->get('shopware_media.media_service');

        foreach ($importer->getList('Attachment') as $xmlAttachment) {
            /** @var \Shopware\Models\Media\Media $media */
            $medias = $mediaRepo->findBy(
                [
                    'name' => $xmlAttachment['name'],
                ]
            );
            $found = false;
            foreach ($medias as $media){
                if($media->getAlbum()->getName() == $xmlAttachment['albumName']){
                    $found = true;
                    break;
                }
            }
            if ($found && $media && $overwriteAttachments) {
                $mediaService->write($xmlAttachment['path'], $importer->readFile($xmlAttachment['file']));
                $thumbnailManager->createMediaThumbnail($media);
                $this->printMessage("Attachment file <info>" . $xmlAttachment['path'] . "</info> has been replaced by <comment>".$xmlAttachment['file']."</comment>");
            } else {
                $album = $albumRepo->findOneBy(['name' => $xmlAttachment['albumName']]);
                if (empty($album)) {
                    $this->printError('Album "'.$xmlAttachment['albumName'].'" not found. Can not import attachment file "'.$xmlAttachment['file'].'"!');
                    continue;
                }

                $sourceFile = $importer->getPath() . $xmlAttachment['file'];
                $mediaService->write($xmlAttachment['path'], @file_get_contents($sourceFile));
                $destiFile = Shopware()->DocPath() . $mediaService->encode($xmlAttachment['path']);

                /** @var \Shopware\Models\Media\Media $newMedia */
                $newMedia = $mediaResource->create([
                    'file' => $xmlAttachment['path'],
                    'description' => $xmlAttachment['name'],
                    'album' => $album->getId(),
                    'name' => $xmlAttachment['name']
                ]);
                $this->printMessage("Attachment file <info>".$newMedia->getFileName()."</info> created from <comment>".$xmlAttachment['file'].'</comment>');

                /* @var $db \Enlight_Components_Db_Adapter_Pdo_Mysql */
                $db = Shopware()->Container()->get('db');
                $db->exec("INSERT INTO s_core_config_mails_attachments (mailID, mediaID, shopID) VALUES (".$dbEmail->getId().", ".$newMedia->getId().", null);");
                $newAttachment = new Attachment($dbEmail, $newMedia);
                Shopware()->Models()->persist($newAttachment);
            }
        }
    }

    /**
     * @param $shopId
     * @param $emailId
     * @param MndConfigImporter $importer
     */
    private function importTranslation($shopId, $emailId, $importer) {
        $translationComponent = new \Shopware_Components_Translation();

        $arrTemplate = $translationComponent->read($shopId, 'config_mails', $emailId);
        $newData = array(
            'subject' => $importer->getText('Subject'),
            'fromMail' => $importer->getText('FromMail'),
            'fromName' => $importer->getText('FromName')
        );
        if ($importer->getAttribute('', 'html')) {
            $newData['contentHtml'] = $importer->readFile('', 'html');
        }
        if ($importer->getAttribute('', 'text')) {
            $newData['content'] = $importer->readFile('', 'text');
        }
        foreach ($newData as $key => $value) {
            if (!empty($value)) {
                $arrTemplate[$key] = $value;
            } else {
                unset($arrTemplate[$key]);
            }
        }
        $translationComponent->write($shopId, 'config_mails', $emailId, $arrTemplate);
    }

    /**
     * @param MndConfigImporter $importer
     * @param bool $ignoreAttachments
     * @param bool $overwriteAttachments
     * @throws \Exception
     */
    private function importMails($importer, $ignoreAttachments, $overwriteAttachments) {
        $mailRepository = Shopware()->Models()->getRepository('Shopware\Models\Mail\Mail');

        foreach ($importer->getList('Email') as $xmlEmail) {
            if (isset($xmlEmail['name'])) {
                /** @var \Shopware\Models\Mail\Mail $dbEmail */
                $dbEmail = $mailRepository->findOneBy(array(
                    'name' => $xmlEmail['name']
                ));
                if (!$dbEmail) {
                    $this->printError("Mailelement " . $xmlEmail['name'] . " does not exist in DB");
                    continue;
                }
                $dbEmail->setIsHtml($xmlEmail['isHtml'] ? true : false);

                $importer->changeCursor($xmlEmail);
                $importer->cursorInto('Templates');
                foreach ($importer->getList('Template') as $xmlTemplate) {
                    $importer->changeCursor($xmlTemplate);
                    if ($xmlTemplate['shopId'] == 1) {
                        $importer->setProp($dbEmail, 'FromMail');
                        $importer->setProp($dbEmail, 'FromName');
                        $importer->setProp($dbEmail, 'Subject');

                        if ($xmlTemplate['html']) {
                            $dbEmail->setContentHtml($importer->readFile('','html'));
                        }
                        if ($xmlTemplate['text']) {
                            $dbEmail->setContent($importer->readFile('','text'));
                        }
                    } else {
                        $this->importTranslation($xmlEmail['shopId'], $dbEmail->getId(), $importer);
                    }
                    $importer->cursorOut();
                }
                $importer->cursorOut();
                if (!$ignoreAttachments) {
                    $importer->cursorInto('Attachments');
                    $this->importAttachments($importer, $dbEmail, $overwriteAttachments);
                    $importer->cursorOut();
                }

                Shopware()->Models()->persist($dbEmail);
                Shopware()->Models()->flush();
                $this->printMessage("email imported: <comment>" . $dbEmail->getName() . "</comment>");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $ignoreAttachments = $input->getOption('ignore-attachments');
        $overwriteAttachments = $input->getOption('overwrite-attachments');

        $finder = new Finder();

        /** @var Finder[] $files */
        $files = $finder->files()->in($this->path)->name(self::CONFIG_FILE);

        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($files as $file) {
            try {
                $importer = new MndConfigImporter($file->getPathname());
                $this->printInfo("\nimport file " . $file->getPathname());
                $this->importMails($importer, $ignoreAttachments, $overwriteAttachments);
            } catch (WrongRootException $e) {
                 // wrong root tag
            } catch (MismatchedVersionException $e) {
                // wrong version of plugin or shopware
                $this->printError('unmatched version! skipped file ' . $file->getPathname());
            }
        }
    }

    private function base64_encode_png_image($path) {
        if ($path) {
            $imgbinary = fread(fopen($path, "r"), filesize($path));
            return 'data:image/png;base64,' . base64_encode($imgbinary);
        }
    }
}