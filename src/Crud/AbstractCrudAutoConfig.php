<?php

declare(strict_types=1);

namespace Lle\CruditBundle\Crud;

use Lle\CruditBundle\Dto\{
    Layout\LinkElement,
    Icon,
    Path
};
use Lle\CruditBundle\Contracts\{
    MenuProviderInterface,
    CrudConfigInterface
};

abstract class AbstractCrudAutoConfig extends AbstractCrudConfig implements CrudConfigInterface, MenuProviderInterface
{

    public function getController(): ?string
    {
        return null;
    }

    public function getMenuEntry(): iterable
    {
        yield $this->getLinkElement();
    }

    public function getLinkElement(): LinkElement
    {
        return LinkElement::new(
            ucfirst(str_replace('-', ' ', $this->getName() ?? 'menu')),
            Path::new('lle_crudit_crud_index', ['resource' => $this->getName()]),
            Icon::new('circle', Icon::TYPE_FAR)
        );
    }

    public function getName(): ?string
    {
        $className = explode('\\', get_class($this));
        return str_replace(
            ['-crud-config', '-config', '-crud'],
            '',
            ltrim(
                strtolower(
                    join(
                        '-',
                        (array) preg_split('/(?=[A-Z])/', $className[\count($className) - 1])
                    )
                ),
                '-'
            )
        );
    }
}
