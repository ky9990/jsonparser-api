<?php

namespace Keboola\JsonparserApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller,
	Symfony\Component\HttpKernel\Exception\HttpException,
	Symfony\Component\HttpFoundation\Response,
	Symfony\Component\HttpFoundation\Request;
use Keboola\JsonparserApiBundle\JsonparserApi;

/**
 * @TODO:
 *	- upload to S3 & generate temporary secure link that'll be sent by mail
 *	- send file as attachment
 *
 */
class ApiController extends Controller
{
	/**
	 * @param Request $request
	 */
	public function convertAction(Request $request)
	{
		if ($request->getContentType() == "json") {
		// for JSON in a request body
			$data = $request->getContent();
		} elseif (array() !== $request->files->all()) {
		// JSON as a file attachment
			if (count($request->files->all()) > 1) {
				throw new HttpException(400, "Only one file at a time is supported using form-data");
			}

			$file = array_values($request->files->all())[0];
			if ($file->getClientSize() > 2*1024*1024) { // FIXME parameters.yml bsns
				throw new HttpException(400, "Uploaded file exceeded 2MB");
			}

			$data = file_get_contents($file->getPathName());
		} else {
			throw new HttpException(400, "Request data must be in a JSON format!");
		}

		$api = new JsonparserApi();
		$result = $api->process($data);

		return $this->returnFileResponse($result);
	}

	public function convertLineDelimitedAction(Request $request)
	{
		if (array() == $request->files->all()) {
			throw new HttpException(400, "No files have been attached in the form-data");
		}
		if (count($request->files->all()) > 1) {
			throw new HttpException(400, "Only one file at a time is supported using form-data");
		}

		$file = array_values($request->files->all())[0];

		$api = new JsonparserApi();
		$result = $api->processLineDelimited($file->openFile('r'));

		return $this->returnFileResponse($result);
	}

	/**
	 * @param Request $request
	 */
	public function getAction(Request $request, $ld = false)
	{
		parse_str($request->getQueryString(), $query);
		$url = urldecode($query['url']);
		$api = new JsonparserApi();
		$result = $api->processFromUrl($url, true);

		return $this->returnFileResponse($result);
	}

	protected function returnFileResponse($result)
	{
		$response = new Response();

		// Set headers
		$response->headers->set('Cache-Control', 'private');

		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mimetype = $finfo->file($result["pathname"]);
		$response->headers->set('Content-type', $mimetype);

		$response->headers->set('Content-Disposition', "attachment; filename=\"{$result["name"]}\";");
		$response->headers->set('Content-length', filesize($result["pathname"]));

		// Send headers before outputting anything
		$response->sendHeaders();

		$response->setContent(readfile($result["pathname"]));

		return $response;
	}
}
