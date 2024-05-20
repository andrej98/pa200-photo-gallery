<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class RedirectController extends AbstractController
{
    #[Route('/', name: 'index_redirect')]
    public function indexRedirect(): RedirectResponse
    {
        return $this->redirectToRoute('photo_index');
    }
}
