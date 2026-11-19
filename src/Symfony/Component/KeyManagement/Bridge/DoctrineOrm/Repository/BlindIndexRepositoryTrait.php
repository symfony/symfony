<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Repository;

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Resolves the blind indexes in the criteria passed to findBy(), findOneBy() and matching().
 *
 * An encrypted column cannot be compared, so the criteria naming its source property are rewritten
 * to the tag column and value before the query runs. Renaming an indexed property, or the column
 * holding its tags, therefore changes nothing at the call site.
 *
 * The resolver is injected through the #[Required] setter, so the repository service must be
 * autowired. A property indexed by more than one blind index is refused: the call site has to name
 * the tag column of the index it wants to search.
 *
 * matching() translates equality, inequality, IN, NIN and null comparisons. Ordering and partial
 * comparisons are refused, since a tag preserves neither. DQL and the query builder are not
 * covered, and a bulk update still leaves the tags it does not write stale.
 *
 * @phpstan-require-extends \Doctrine\ORM\EntityRepository
 *
 * @psalm-require-extends \Doctrine\ORM\EntityRepository
 *
 * @experimental
 */
trait BlindIndexRepositoryTrait
{
    private BlindIndexResolver $blindIndexResolver;

    #[Required]
    public function setBlindIndexResolver(BlindIndexResolver $blindIndexResolver): void
    {
        $this->blindIndexResolver = $blindIndexResolver;
    }

    public function findBy(#[\SensitiveParameter] array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        if (isset($this->blindIndexResolver) && $criteria) {
            $criteria = $this->blindIndexResolver->resolve($this->getClassName(), $criteria);
        }

        return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    public function findOneBy(#[\SensitiveParameter] array $criteria, ?array $orderBy = null): ?object
    {
        if (isset($this->blindIndexResolver) && $criteria) {
            $criteria = $this->blindIndexResolver->resolve($this->getClassName(), $criteria);
        }

        return parent::findOneBy($criteria, $orderBy);
    }

    public function matching(Criteria $criteria): AbstractLazyCollection&Selectable
    {
        if (isset($this->blindIndexResolver)) {
            $criteria = $this->blindIndexResolver->resolveCriteria($this->getClassName(), $criteria);
        }

        return parent::matching($criteria);
    }
}
