<?php

declare(strict_types=1);

namespace Example\Controllers;

use Melodic\Controller\MvcController;
use Melodic\Http\Response;

class HomeController extends MvcController
{
    public function index(): Response
    {
        $this->viewBag->title = 'Home';
        $this->viewBag->userContext = $this->getUserContext();
        $this->setLayout('layouts/main');

        return $this->view('home/index', [
            'message' => 'Welcome to the Melodic PHP Framework!',
        ]);
    }

}
