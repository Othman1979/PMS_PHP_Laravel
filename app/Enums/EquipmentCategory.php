<?php

namespace App\Enums;

enum EquipmentCategory: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'Category_';

    case Refrigeration = 'Refrigeration';
    case KitchenEquipment = 'KitchenEquipment';
    case Administrative = 'Administrative';
    case SafetySystems = 'SafetySystems';
    case Other = 'Other';
}
