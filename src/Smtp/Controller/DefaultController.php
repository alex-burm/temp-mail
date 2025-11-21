<?php

namespace App\Smtp\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('@smtp/index.html.twig');
    }

    #[Route('/spf-checker', name: 'spf-checker')]
    public function spfChecker(): Response
    {
        return $this->render('@smtp/spf.html.twig');
    }

    #[Route('/dkim-checker', name: 'dkim-checker')]
    public function dkimChecker(): Response
    {
        return $this->render('@smtp/dkim.html.twig');
    }

    #[Route('/dmarc-checker', name: 'dmarc-checker')]
    public function dmarcChecker(): Response
    {
        return $this->render('@smtp/dmarc.html.twig');
    }
}
