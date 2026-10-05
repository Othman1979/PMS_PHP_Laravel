<?php

namespace App\Enums;

enum Role: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'Role_';

    case Admin = 'Admin';
    case Coordinator = 'Coordinator';
    case Technician = 'Technician';
    case DepartmentManager = 'DepartmentManager';
    case Employee = 'Employee';
    case FoodSafety = 'FoodSafety';
}
