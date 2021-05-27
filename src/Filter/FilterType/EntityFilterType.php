<?php

namespace Lle\CruditBundle\Filter\FilterType;

use Doctrine\ORM\QueryBuilder;

class EntityFilterType extends AbstractFilterType
{
    private string $entityClass;

    public static function new(string $fieldname, $entityClass): self
    {
        $f = new self($fieldname);
        $f->setEntityClass($entityClass);
        $f->setAdditionnalKeys(['items']);
        return $f;
    }

    public function setEntityClass(string $classname) {
        $this->entityClass = $classname;
    }

    public function getOperators() : array
    {
        return [
            "eq" => ["icon" => "fas fa-equals"],
            "neq" => ["icon" => "fas fa-not-equal"],
        ];
    }

    public function apply(QueryBuilder $queryBuilder) : void
    {
        if (isset($this->data['value'])) {
            $ids = explode(',', $this->data['value']);
            $queryBuilder->andWhere($queryBuilder->expr()->in($this->alias . $this->columnName, ':var_' . $this->id));
            $queryBuilder->setParameter('var_' . $this->id, $ids);
        }
    }

    public function getDataRoute(): string {
        $route = str_replace("App\\Entity\\","",$this->entityClass);
        return "app_crudit_".strtolower($route)."_autocomplete";
    }

    public function getStateTemplate(): string
    {
        return '@LleCrudit/filter/state/entity_filter.html.twig';
    }

    public function getTemplate(): string
    {
        return '@LleCrudit/filter/type/entity_filter.html.twig';
    }
}
