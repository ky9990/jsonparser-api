<?php

namespace Keboola\JsonparserApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller,
	Symfony\Component\HttpKernel\Exception\HttpException,
	Symfony\Component\HttpFoundation\Response,
	Symfony\Component\HttpFoundation\Request;
use Keboola\JsonparserApiBundle\JsonparserApi;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @TODO:
 *	- upload to S3 & generate temporary secure link that'll be sent by mail
 *	- send file as attachment
 *
 */
class ApiController extends Controller
{
    public function healthAction(Request $request)
    {
        $response = new JsonResponse();
        $response->setData([
            'status' => 'ok',
        ]);
        return $response;
    }

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


            try {
                $result = $api->process($data);
            } catch (\Exception $e) {
                throw new HttpException(400, 'Error on JSON processing: ' . $e->getMessage());
            }

        } elseif ($request->getContentType() == "json") {
      		// for JSON in a request body
      		$data = $request->getContent();
            if ($linedelimited) {
                throw new HttpException(400, "Line delimited allowed only for file uploads.");
            }


            try {
                $result = $api->process($data);
            } catch (\Exception $e) {
                throw new HttpException(400, 'Error on JSON processing: ' . $e->getMessage());
            }

        } else {
		    // JSON as a file attachment
			if (count($request->files->all()) > 1) {
				throw new HttpException(400, "Only one file at a time is supported using form-data");
			}
            if (array() == $request->files->all()) {
                throw new HttpException(400, "No files have been attached in the form-data");
            }
			$file = array_values($request->files->all())[0];
			if ($file->getClientSize() > 10*1024*1024) { // FIXME parameters.yml bsns
				throw new HttpException(400, "Uploaded file exceeded 10MB");
			}
            $file = array_values($request->files->all())[0];

            try {
                if ($linedelimited) {
                    $result = $api->processLineDelimited($file->openFile('r'));
                } else {
                    $data = file_get_contents($file->getPathName());
                    $result = $api->process($data);
                }
            } catch (\Exception $e) {
                throw new HttpException(400, 'Error on JSON processing: ' . $e->getMessage());
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

        try {
            $result = $api->processFromUrl($url, $linedelimited);
            return $this->returnFileResponse($result);
        } catch (\Exception $e) {
            throw new HttpException(400, 'Error on JSON processing: ' . $e->getMessage());
        }
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
