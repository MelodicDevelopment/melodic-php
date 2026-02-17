<?php

declare(strict_types=1);

namespace MelodicWeb\Controllers;

use Melodic\Controller\MvcController;
use Melodic\Http\Response;

class HomeController extends MvcController
{
    public function index(): Response
    {
        $this->viewBag->title = 'Melodic PHP Framework — Modern PHP built for clarity';
        $this->setLayout('layouts/marketing');

        return $this->view('pages/home');
    }

    public function whyMelodic(): Response
    {
        $this->viewBag->title = 'Why Melodic — Philosophy & Comparison';
        $this->setLayout('layouts/marketing');

        return $this->view('pages/why-melodic');
    }
}
