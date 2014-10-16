<?php

namespace Keboola\JsonparserApiBundle;

use Keboola\Json\Parser;
use Keboola\Utils\Utils;
use Keboola\Temp\Temp;
use Monolog\Logger;

class JsonparserApi
{
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

	protected function zipResults($files)
	{
		$temp = new Temp('jsonparser');
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
}
