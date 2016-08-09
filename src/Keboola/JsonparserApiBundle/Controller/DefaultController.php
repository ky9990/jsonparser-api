<?php

namespace Keboola\JsonparserApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('KeboolaJsonparserApiBundle:Default:index.html.twig', [
            'jsonApiVersion' => JSON_API_VERSION,
        ]);
    }
}
