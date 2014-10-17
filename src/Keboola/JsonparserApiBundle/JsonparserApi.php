<?php

namespace Keboola\JsonparserApiBundle;

use Keboola\Json\Parser;
use Keboola\Utils\Utils;
use Keboola\Temp\Temp;
use Monolog\Logger;
Use GuzzleHttp\Client as Guzzle;

class JsonparserApi
{
	/** @var Temp */
	protected $temp;

	public function process($json) {
		$logger = new Logger('jsonparser');
		$parser = new Parser($logger);

		$data = Utils::json_decode($json);
		if (!is_array($data)) {
			$data = array($data);
		}

		$parser->process($data);

		return $this->zipResults($parser->getCsvFiles());
	}

	public function processLineDelimited(\SplFileObject $file)
	{
		$logger = new Logger('jsonparser');
		/** @type Parser $parser */
		$parser = new Parser($logger);

		while (!$file->eof()) {
			$data = Utils::json_decode($file->fgets());
			if (!is_array($data)) {
				$data = array($data);
			}
			$parser->process($data);
		}

		return $this->zipResults($parser->getCsvFiles());
	}

	public function processFromUrl($url, $delimited = false)
	{
		$temp = $this->getTemp();
		$file = $temp->createTmpFile();
		$guzzle = new Guzzle();
		$guzzle->get(
			$url,
			[ 'save_to' => $file->getPathName() ]
		);

		if (!$delimited) {
			return $this->process(file_get_contents($file->getPathName()));
		} else {
			return $this->processLineDelimited($file->openFile('r'));
		}
	}

	protected function zipResults($files)
	{
		$temp = $this->getTemp();
		$archive = new \ZipArchive();
		$resultFile = $temp->createFile("/results.zip");
		$resultPathName = $resultFile->getPathName();

		if ($archive->open($resultPathName, \ZipArchive::CREATE) !== true) {
			throw new \RuntimeException("Failed creating a ZIP archive!");
		}

		foreach($files as $csvFile) {
// 			$logger->info("Adding " . $csvFile->getName() . " into {$resultPathName}");
			$archive->addFile($csvFile->getPathName(), $csvFile->getName() . ".csv");
		}
		$archive->close();

		return array(
			"pathname" => $resultPathName,
			"name" => "results.zip",
			"temp" => $temp // workaround to prevent deletion. Hints at using S3 as a better option?
		);
	}

	/**
	 * @return Temp
	 */
	protected function getTemp()
	{
		if (empty($this->temp)) {
			$this->temp = new Temp('jsonparser');
		}

		return $this->temp;
	}
}
