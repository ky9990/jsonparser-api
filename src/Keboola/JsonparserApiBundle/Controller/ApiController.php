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
     * @return Response
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function postAction(Request $request)
    {
        $linedelimited = false;
        if ($request->get("linedelimited")) {
            $linedelimited = true;
        }
        $api = new JsonparserApi();

        if ($request->get("json")) {
            $data = $request->get("json");
            if ($linedelimited) {
                throw new HttpException(400, "Line delimited allowed only for file uploads.");
            }
            $result = $api->process($data);
        } elseif ($request->getContentType() == "json") {
      		// for JSON in a request body
      		$data = $request->getContent();
            if ($linedelimited) {
                throw new HttpException(400, "Line delimited allowed only for file uploads.");
            }
            $result = $api->process($data);
        } else {
		    // JSON as a file attachment
			if (count($request->files->all()) > 1) {
				throw new HttpException(400, "Only one file at a time is supported using form-data");
			}
            if (array() == $request->files->all()) {
                throw new HttpException(400, "No files have been attached in the form-data");
            }
			$file = array_values($request->files->all())[0];
			if ($file->getClientSize() > 2*1024*1024) { // FIXME parameters.yml bsns
				throw new HttpException(400, "Uploaded file exceeded 2MB");
			}
            $file = array_values($request->files->all())[0];
            if ($linedelimited) {
                $result = $api->processLineDelimited($file->openFile('r'));
            } else {
                $data = file_get_contents($file->getPathName());
                $result = $api->process($data);
            }
        }
        return $this->returnFileResponse($result);

    }

    /**
     * @param Request $request
     * @return Response
     */
    public function getAction(Request $request)
   	{
   		parse_str($request->getQueryString(), $query);
   		$url = urldecode($query['url']);
        $linedelimited = false;
        if (isset($query['linedelimited'])) {
            $linedelimited = true;
        }
   		$api = new JsonparserApi();
   		$result = $api->processFromUrl($url, $linedelimited);

   		return $this->returnFileResponse($result);
   	}

    /**
     * @param $result
     * @return Response
     */
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
