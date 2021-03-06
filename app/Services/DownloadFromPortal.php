<?php

namespace App\Services;

use Storage;
use Exception;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Stream;

class DownloadFromPortal extends Downloader
{
    private $backupDir = 'backupPortal';

    private $downloader;

    private $command;

    public function __construct()
    {
        $this->downloader = new Downloader();

        $this->guzzle = new Client();
    }

    private function configureCommand($command)
    {
        $this->command = $command;
    }

    private function downloadFile($fileUrl, $baseDirName)
    {
        $fileUrl = trim(str_replace('http://www.portalalerj.rj.gov.br/', 'http://www.alerj.rj.gov.br/', $fileUrl));

        if (! starts_with($fileUrl, 'http'))
        {
            $fileUrl = 'http://'.$fileUrl;
        }

        $fileName = $this->getBasedirFromDomain($fileUrl);

        $fileName = $this->makeFileName($baseDirName, 'imagens', $fileName);

        if (Storage::disk('local')->exists($fileName))
        {
            return true;
        }

        try
        {
            $contents = $this->downloadFromUrl($fileUrl);

            $file = Storage::disk('local')->put($fileName, $contents);

            $this->info('Downloaded: '.$fileName);

            return $file;
        }
        catch (Exception $exception)
        {
            $this->error($exception->getMessage());
        }

        return null;
    }

    private function downloadFromUrl($fileUrl)
    {
        $tempFile = tmpfile();

        $stream = new Stream($tempFile);

        $this->guzzle->request('GET', $fileUrl, ['sink' => $stream]);

        $contents = $stream->getContents();

        fclose($tempFile);

        return $contents;
    }

    public function downloadNews()
    {
        $dirName = 'noticias';

        $news = $this->fetchNews()->toArray();

        $this->saveJson($dirName);

        $this->savePhotos($news, $dirName);
    }

    public function downloadSchedule()
    {
        $dirName = 'agenda';

        $news = $this->fetchSchedule()->toArray();

        $this->saveJson($dirName);

        $this->savePhotos($news, $dirName);
    }

    public function execute($command = null)
    {
        $this->configureCommand($command);

        $this->downloadDeputies();
        $this->downloadNews();
        $this->downloadSchedule();
    }

    /**
     * @param $items
     * @return mixed
     */
    private function extractCsv($items)
    {
        $lines[0] = $this->makeCsvLine(array_keys($items[0]));

        foreach ($items as $item)
        {
            $lines[] = $this->makeCsvLine($item);
        }

        return $lines;
    }

    public function fetchDeputies()
    {
        return $this->downloader->fetch('apialerj.rj.gov.br/api/deputadoservice');
    }

    public function downloadDeputies()
    {
        $dirName = 'deputados';

        $deputies = $this->fetchDeputies()->toArray();

        $this->saveJson($dirName);

        $this->savePhotos($deputies, $dirName);
    }

    private function fetchNews()
    {
        return $this->downloader->fetch('apialerj.rj.gov.br/api/noticiaservice');
    }

    private function fetchSchedule()
    {
        return $this->downloader->fetch('apialerj.rj.gov.br/api/agendaservice');
    }

    private function getBasedirFromDomain($url)
    {
        $parsed = parse_url($url);

        return $parsed['path'];
    }

    private function getFileDate()
    {
        return Carbon::now()->format('Ymd-his');;
    }

    private function getMulimedia($columns)
    {
        foreach ($columns as $column)
        {
            
        }
    }

    private function info($string)
    {
        if ($this->command)
        {
            $this->command->info($string);
        }
    }

    private function error($string)
    {
        if ($this->command)
        {
            $this->command->error($string);
        }
    }

    /**
     * @param $deputy
     * @param $lines
     * @return array
     */
    private function makeCsvLine($deputy)
    {
        $line = "";

        foreach ($deputy as $key => $column)
        {
            if ($key === "Multimidias" && is_array($column))
            {
                $column = $this->getMulimedia($column);
            }

            $line .= ($line ? ';' : '') . $column;
        }

        return $line;
    }

    /**
     * @param $dirName
     * @return mixed
     */
    private function makeFileName($dirName, $kind, $fileAndExtension)
    {
        $fileName = sprintf('%s/%s/%s/%s', $this->backupDir, $dirName, $kind, $fileAndExtension);

        return $fileName;
    }

    /**
     * @param $dirName
     */
    private function saveJson($dirName)
    {
        $fileName = $this->makeFileName($dirName, 'json', $dirName . '-' . $this->getFileDate() . '.json');

        $this->info('JSON saved: '.$fileName);

        Storage::disk('local')->put($fileName, $this->downloader->toJson());
    }

    private function savePhotos($lines, $baseDirName)
    {
        foreach ($lines as $line)
        {
            if (isset($line['Foto']))
            {
                $this->downloadFile($line['Foto'], $baseDirName);
            }

            if (isset($line['Multimidias']))
            {
                foreach ($line['Multimidias'] as $item)
                {
                    $this->downloadFile($item['Arquivo'], $baseDirName);
                }
            }
        }
    }
}
