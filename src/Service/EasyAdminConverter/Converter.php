<?php

namespace Lle\CruditBundle\Service\EasyAdminConverter;

use Lle\CruditBundle\Dto\Field\Field;
use Lle\CruditBundle\Maker\MakeCrudit;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Generator;

class Converter
{
    protected $logs = [];

    protected Generator $generator;

    protected MakeCrudit $cruditMaker;

    protected DoctrineHelper $doctrineHelper;

    public function __construct(
        Generator $generator,
        MakeCrudit $cruditMaker,
        DoctrineHelper $doctrineHelper
    )
    {
        $this->generator = $generator;
        $this->cruditMaker = $cruditMaker;
        $this->doctrineHelper = $doctrineHelper;
    }

    public function convert(array $config): iterable
    {
        $ignoreDuplicates = [];

        if (isset($config["entities"])) {
            foreach ($config["entities"] as $short => $entityConfig) {
                if (isset($ignoreDuplicates[$entityConfig["class"]])) {
                    // EasyAdmin allows to define multiple CRUDs for a single entity
                    // we don"t bother handling that case in Crudit and simply
                    // warn the user
                    yield "warning" => "Duplicate detected for " . $entityConfig["class"] . " (" . $short . "). Please check the CrudConfig and the FormType.";
                    continue;
                }

                if (isset($entityConfig["filter"])) {
                    yield from $this->makeFilterSet($entityConfig);
                }
                yield from $this->makeDatasource($entityConfig);
                yield from $this->makeCrudConfig($entityConfig);
                yield from $this->makeController($entityConfig);
                yield from $this->makeFormType($entityConfig);

                $ignoreDuplicates[$entityConfig["class"]] = true;
            }
        }

        yield from $this->makeMenu($config);

        yield "success" => "Conversion finished ! Please note the warnings above and fix them.";
        yield "warning" => "Note that the converter is not perfect. Some things were ignored, some things were modified. You need to check all pages.";
    }

    protected function makeFilterSet(array $entityConfig): iterable
    {
        $entityClass = $entityConfig["class"];
        $shortEntity = $this->getShortEntityName($entityClass);

        $filters = [];
        $metadata = $this->doctrineHelper->getMetadata($entityClass);
        foreach ($entityConfig["filter"]["fields"] as $filter) {
            $filters[] = $this->cruditMaker->getFilterType($metadata, $filter["property"]);
        }

        $uses = [];
        foreach ($filters as $filter) {
            array_push($uses, ...$filter["uses"]);
        }
        $uses = array_unique($uses);
        sort($uses);

        $filtersetClassNameDetails = $this->generator->createClassNameDetails(
            $shortEntity,
            "Crudit\Datasource\Filterset\\",
            "FilterSet"
        );

        $this->generator->generateClass(
            $filtersetClassNameDetails->getFullName(),
            $this->cruditMaker->getSkeletonTemplate("filterset/EntityFilterset.php"),
            [
                "namespace" => "App",
                "entityClass" => $shortEntity,
                "fullEntityClass" => $entityClass,
                "filters" => $filters,
                "strictType" => true,
                "uses" => $uses,
            ]
        );
        $this->generator->writeChanges();

        yield;
    }

    protected function makeDatasource(array $entityConfig): iterable
    {
        $entityClass = $entityConfig["class"];
        $shortEntity = $this->getShortEntityName($entityClass);

        $datasourceClassNameDetails = $this->generator->createClassNameDetails(
            $shortEntity,
            "Crudit\\Datasource\\",
            "Datasource"
        );
        $this->generator->generateClass(
            $datasourceClassNameDetails->getFullName(),
            $this->cruditMaker->getSkeletonTemplate("datasource/DoctrineDatasource.php"),
            [
                "namespace" => "App",
                "entityClass" => $shortEntity,
                "hasFilterset" => isset($entityConfig["filter"]),
                "fullEntityClass" => $entityClass,
                "strictType" => true,
            ]
        );
        $this->generator->writeChanges();

        yield;
    }

    protected function makeCrudConfig(array $entityConfig): iterable
    {
        $entityClass = $entityConfig["class"];
        $shortEntity = $this->getShortEntityName($entityClass);
        $fields = [];
        $cruds = [];

        foreach ($entityConfig["list"]["fields"] ?? [] as $property) {
            if (!isset($property["property"])) {
                yield "warning" => "Property " . http_build_query($property, "", " ") . " ignored";
                continue;
            }

            $sortable = isset($property["sortable"]) ? $property["sortable"] : true;

            // the duplicate field is on purpose
            $fields[] = Field::new($property["property"]);
            $cruds["CrudConfigInterface::INDEX"][] = Field::new($property["property"])
                ->setSortable($sortable);
        }

        foreach ($entityConfig["show"]["fields"] ?? [] as $property) {
            if (!isset($property["property"])) {
                yield "warning" => "Property " . http_build_query($property, "", " ") . " ignored";
                continue;
            }

            // the duplicate field is on purpose
            $fields[] = Field::new($property["property"]);
            $cruds["CrudConfigInterface::SHOW"][] = Field::new($property["property"]);
        }

        foreach ($entityConfig["export"]["fields"] ?? [] as $property) {
            if (!isset($property["property"])) {
                yield "warning" => "Property " . http_build_query($property, "", " ") . " ignored";
                continue;
            }

            // the duplicate field is on purpose
            $fields[] = Field::new($property["property"]);
            $cruds["CrudConfigInterface::EXPORT"][] = Field::new($property["property"]);
        }

        $forms = [];
        if (!isset($entityConfig["form"])) {
            if (isset($entityConfig["edit"])) {
                $forms["CrudConfigInterface::EDIT"] = "Edit";
            }
            if (isset($entityConfig["new"])) {
                $forms["CrudConfigInterface::NEW"] = "New";
            }
        }

        $tabs = [];
        if (isset($entityConfig["show"]["fields"])) {
            $tabs = $this->getTabs($entityConfig);
        }

        $sort = [];
        if (isset($entityConfig["list"]["sort"])) {
            $sort["property"] = $entityConfig["list"]["sort"][0];
            $sort["order"] = $entityConfig["list"]["sort"][1];
        }

        $configuratorClassNameDetails = $this->generator->createClassNameDetails(
            $shortEntity,
            "Crudit\\Config\\",
            "CrudConfig"
        );

        $this->generator->generateClass(
            $configuratorClassNameDetails->getFullName(),
            $this->cruditMaker->getSkeletonTemplate("config/CrudAutoConfig.php"),
            [
                "namespace" => "App",
                // array_unique with flag SORT_REGULAR will compare object properties.
                "fields" => array_unique($fields, SORT_REGULAR),
                "cruds" => $cruds,
                "entityClass" => $shortEntity,
                "fullEntityClass" => $entityClass,
                "strictType" => true,
                "forms" => $forms,
                "tabs" => $tabs,
                "sort" => $sort,
                "controllerRoute" => "crudit_" . $shortEntity,
            ]
        );
        $this->generator->writeChanges();

        yield;
    }

    protected function makeController(array $entityConfig): iterable
    {
        $entityClass = $entityConfig["class"];
        $shortEntity = $this->getShortEntityName($entityClass);

        $controllerClassNameDetails = $this->generator->createClassNameDetails(
            $shortEntity,
            "Controller\\Crudit\\",
            "Controller"
        );
        $this->generator->generateClass(
            $controllerClassNameDetails->getFullName(),
            $this->cruditMaker->getSkeletonTemplate("controller/CrudController.php"),
            [
                "namespace" => "App",
                "fullEntityClass" => $entityClass,
                "entityClass" => $shortEntity,
                "strictType" => true
            ]
        );
        $this->generator->writeChanges();

        yield;
    }

    protected function makeFormType(array $entityConfig): iterable
    {
        $entityClass = $entityConfig["class"];

        if (isset($entityConfig["form"]["fields"])) {
            $this->addFormType($entityConfig["form"]["fields"], $entityClass, "");
        } else {
            if (isset($entityConfig["edit"]["fields"])) {
                $this->addFormType($entityConfig["edit"]["fields"], $entityClass, "Edit");
            }
            if (isset($entityConfig["new"]["fields"])) {
                $this->addFormType($entityConfig["new"]["fields"], $entityClass, "New");
            }
        }

        $this->generator->writeChanges();

        yield;
    }

    public function makeMenu(array $config): iterable
    {
        if (!isset($config["design"]["menu"])) {
            yield;
        }

        $items = [];

        foreach ($config["design"]["menu"] as $menu) {
            $parent = $this->getMenuItem($config, $menu);
            if (!$parent) {
                continue;
            }
            $items[] = $parent;

            if (isset($menu["children"])) {
                foreach ($menu["children"] as $child) {
                    $item = $this->getMenuItem($config, $child);
                    if (!$item) {
                        continue;
                    }
                    $item["parent"] = $parent["label"];
                    $items[] = $item;
                }
            }
        }

        $className = "MenuProvider";
        $classDetails = $this->generator->createClassNameDetails(
            "MenuProvider",
            "Crudit\\CrudMenu",
        );
        $this->generator->generateClass(
            $classDetails->getFullName(),
            $this->cruditMaker->getSkeletonTemplate("menu/MenuProvider.php"),
            [
                "namespace" => "App",
                "className" => $className,
                "items" => $items,
            ]
        );
        $this->generator->writeChanges();

        yield;
    }

    protected function getMenuItem(array $config, $menu): ?array
    {
        $item = null;
        if (is_string($menu)) {
            // entity
            $entity = $config["entities"][$menu]["class"];

            $item = [
                "label" => "menu." . strtolower($menu),
                "route" => "app_crudit_" . strtolower($this->getShortEntityName($entity)) . "_index",
            ];
        } elseif (is_array($menu)) {
            if (!isset($menu["entity"]) && !isset($menu["url"]) && !isset($menu["route"]) && !isset($menu["children"])) {
                $item = ["type" => "separator"];
                if (isset($menu["role"])) {
                    $item["role"] = $menu["role"];
                }
            } else {
                $item = [
                    "label" => $menu["label"] ?? "menu." . strtolower($menu["entity"]),
                ];

                if (isset($menu["icon"])) {
                    $item["icon"] = $menu["icon"];
                }

                if (isset($menu["entity"])) {
                    $entity = $config["entities"][$menu["entity"]]["class"];
                    $item["route"] = "app_crudit_" . strtolower($this->getShortEntityName($entity)) . "_index";
                }

                if (isset($menu["role"])) {
                    $item["role"] = $menu["role"];
                }

                if (isset($menu["route"])) {
                    $item["route"] = $menu["route"];
                }

                if (isset($menu["url"])) {
                    $item["url"] = $menu["url"];
                }
            }
        }

        return $item;
    }

    protected function addFormType(array $properties, string $entityClass, string $prefix): void
    {
        $shortEntity = $this->getShortEntityName($entityClass);

        $fields = [];
        foreach ($properties as $property) {
            $fields[] = Field::new($property["property"]);
        }

        $formTypeClassNameDetails = $this->generator->createClassNameDetails(
            $prefix . $shortEntity,
            "Form\\",
            "Type"
        );
        $this->generator->generateClass(
            $formTypeClassNameDetails->getFullName(),
            $this->cruditMaker->getSkeletonTemplate("form/EntityCruditType.php"),
            [
                "namespace" => "App",
                "entityClass" => $prefix . $shortEntity,
                "fields" => $fields,
                "strictType" => true
            ]
        );
    }

    protected function getTabs(array $entityConfig): array
    {
        $tabs = [];
        foreach ($entityConfig["show"]["fields"] as $field) {
            if (is_array($field) && isset($field["type"])) {
                if ($field["type"] === "sublist") {
                    $metadata = $this->doctrineHelper->getMetadata($entityConfig["class"]);
                    // "No mapping found for field ..." => your sublist property does not exist

                    $association = null;
                    if (!isset($field["property"])) {
                        foreach ($metadata->getAssociationMappings() as $mapping) {
                            if ($this->getShortEntityName($mapping["targetEntity"]) === $field["entity"]) {
                                $association = $mapping;
                                break;
                            }
                        }
                    } else {
                        $association = $metadata->getAssociationMapping($field["property"]);
                    }

                    if ($association) {
                        $mappedBy = $association["mappedBy"];

                        $tabs[] = [
                            "type" => "sublist",
                            "label" => $field["label"] ?? "tab." . strtolower($field["property"]),
                            "property" => $mappedBy,
                            "linkedEntity" => $this->getShortEntityName($association["targetEntity"]),
                        ];
                    }
                } elseif ($field["type"] === "tab" && isset($field["action"]) && $field["action"] === "historyAction") {
                    $tabs[] = [
                        "type" => "history",
                        "label" => $field["label"] ?? "tab.history",
                    ];
                }
            }
        }

        return $tabs;
    }

    protected function getShortEntityName(string $class): string
    {
        return basename(str_replace("\\", "/", $class));
    }
}