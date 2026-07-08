<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/* Permet permet de filtrer 'Voyage' et 'Depannage' par 'personnel.id' via la table pivot 'Detailpersonnel'
 */
final class PersonnelFilter extends AbstractFilter
{
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void
    {
        if($property !== 'personnel.id') { /*
            - On n'intercepte que 'personnel.id'
        */
            return;
        }

        if(!$value || !is_numeric($value)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $dpAlias = $queryNameGenerator->generateJoinAlias('detailpersonnels');
        $paramName = $queryNameGenerator->generateParameterName('personnelId');

        $queryBuilder
            ->innerJoin("{$alias}.detailpersonnels", $dpAlias)
            ->andWhere("{$dpAlias}.personnel = :{$paramName}")
            ->setParameter($paramName, (int)$value)
            ->distinct(); /*
                - 'distinct()' pour éviter que le 'INNER JOIN' retourne le voyage en doublon si un personnel peut être affecté plusieurs fois au même voyage
            */
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'personnel.id' => [
                'property' => 'personnel.id',
                'type' => 'integer',
                'required' => false,
                'description' => 'Filtre par personnel (via Detailpersonnel)',
                'openapi' => [
                    'description' => 'Filtre les résultats liés à un personnel donné',
                    'example' => '3'
                ]
            ]
        ];
    }
}