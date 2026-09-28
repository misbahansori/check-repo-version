<?php

declare(strict_types=1);

namespace App;

use function Tempest\View\view;

use Tempest\Router\Get;
use Tempest\View\View;

final readonly class HomeController
{
    #[Get('/')]
    public function __invoke(): View
    {
        return view('home.view.php');
    }
}
