<?php
namespace WebExcess\Neos\Backup\Controller;

/*
 * This file is part of the WebExcess.Neos.Backup package.
 */

use TYPO3\Flow\Annotations as Flow;
use \TYPO3\Neos\Controller\Module\AbstractModuleController;
use \WebExcess\Flow\Backup\Service\BackupService;
use \WebExcess\Flow\Backup\Output\TextOutput;
use \TYPO3\Flow\Resource\Resource;

/**
 * @Flow\Scope("singleton")
 */
class ModuleController extends AbstractModuleController
{

    /**
     * @Flow\InjectConfiguration(path="Folders.Sources", package="WebExcess.Flow.Backup")
     * @var array
     */
    protected $backupFolders;

    /**
     * @Flow\InjectConfiguration(path="Folders.LocalTarget", package="WebExcess.Flow.Backup")
     * @var array
     */
    protected $localBackupTarget;

    /**
     * @Flow\Inject()
     * @var BackupService
     */
    protected $backupService;

    /**
     * @Flow\Inject()
     * @var TextOutput
     */
    protected $textOutput;

    protected function initializeAction()
    {
        $this->backupService->initialize($this->textOutput);
        parent::initializeAction();
    }


    public function indexAction()
    {
        $availableVersions = $this->backupService->getAvailableVersionsWithInfos();
        //\TYPO3\Flow\var_dump($availableVersions);
        //exit();
        krsort($availableVersions);
        $this->view->assign('availableVersions', $availableVersions);

        $this->view->assign('backupFolders', $this->backupFolders);
        $this->view->assign('localBackupTarget', $this->localBackupTarget);

        $this->view->assign('hasKeyFile', $this->hasKeyFile());
    }

    public function backupStartAction()
    {
        $this->backupService->createBackup();
        //\TYPO3\Flow\var_dump($this->backupService->getOutput()->getText());
        //exit();
        $this->forward('backupFinished');
    }

    public function backupFinishedAction()
    {
        $lines = explode("\n", $this->textOutput->getText());
        $lastTitle = '';
        $formattedLogs = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '<b>')!==false) {
                $lastTitle = strip_tags($line);
            }elseif(!empty($line)) {
                $formattedLogs[$lastTitle][] = $line;
            }
        }
        $this->view->assign('output', $formattedLogs);
    }

    /**
     * @param string $restoreVersion
     * @return void
     */
    public function backupRestoreStartAction($restoreVersion)
    {
        $this->backupService->restoreBackup($restoreVersion, true);
        $this->forward('backupRestoreFinished');
    }

    public function backupRestoreFinishedAction()
    {
        $lines = explode("\n", $this->textOutput->getText());
        $lastTitle = '';
        $formattedLogs = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '<b>')!==false) {
                $lastTitle = strip_tags($line);
            }elseif(!empty($line)) {
                $formattedLogs[$lastTitle][] = $line;
            }
        }
        $this->view->assign('output', $formattedLogs);
    }

    /**
     * @param boolean $force
     * @return void
     */
    public function removeBackupsAction($force = null)
    {
        $this->backupService->removeAllBackups();
        if ($force===true) {
            $this->backupService->removeKeyfile();
        }
        $this->redirect('index');
    }

    public function downloadKeyfileAction() {
        $keyfile = $this->backupService->createFilePath([$this->localBackupTarget, 'key']);
        $content = file_get_contents($keyfile);
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"key\"");
        echo $content;
        exit();
    }

    /**
     * @return void
     */
    public function createKeyfileAction()
    {
        $this->backupService->generateKeyFile();
        $this->redirect('index');
    }

    /**
     * @param Resource $keyfile
     */
    public function uploadKeyfileAction(Resource $keyfile)
    {
        $fileStream = $keyfile->getStream();
        $keyfilePath = $this->backupService->createFilePath([$this->localBackupTarget, 'key']);
        file_put_contents($keyfilePath, $fileStream);
        $this->redirect('index');
    }

    /**
     * @return bool
     */
    private function hasKeyFile()
    {
        $keyfile = $this->backupService->createFilePath([$this->localBackupTarget, 'key']);
        return file_exists($keyfile);
    }

}