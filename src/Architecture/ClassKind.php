<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

enum ClassKind: string
{
    case ClassName = 'class';
    case Interface = 'interface';
    case Trait = 'trait';
    case Enum = 'enum';
}
