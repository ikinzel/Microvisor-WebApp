<?php

namespace App\Twig;

use App\Service\UsdaFoodDataCentralClient;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FdcExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('fdc_food_url', [UsdaFoodDataCentralClient::class, 'publicFoodDetailsUrl']),
        ];
    }
}
