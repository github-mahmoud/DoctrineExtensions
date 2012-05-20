<?php

namespace Gedmo\Tree\Entity\Repository;

use Gedmo\Exception\InvalidArgumentException,
    Gedmo\Tree\Strategy,
    Gedmo\Tree\Strategy\ORM\MaterializedPath,
    Gedmo\Tool\Wrapper\EntityWrapper;

/**
 * The MaterializedPathRepository has some useful functions
 * to interact with MaterializedPath tree. Repository uses
 * the strategy used by listener
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @package Gedmo.Tree.Entity.Repository
 * @subpackage MaterializedPathRepository
 * @link http://www.gediminasm.org
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class MaterializedPathRepository extends AbstractTreeRepository
{
    /**
     * Get tree query builder
     *
     * @param object Root node
     *
     * @return Doctrine\ORM\QueryBuilder
     */
    public function getTreeQueryBuilder($rootNode = null)
    {
        return $this->getChildrenQueryBuilder($rootNode, false, null, 'asc', true);
    }

    /**
     * Get tree query
     *
     * @param object Root node
     *
     * @return Doctrine\ORM\Query
     */
    public function getTreeQuery($rootNode = null)
    {
        return $this->getTreeQueryBuilder($rootNode)->getQuery();
    }

    /**
     * Get tree
     *
     * @param object Root node
     *
     * @return array
     */
    public function getTree($rootNode = null)
    {
        return $this->getTreeQuery($rootNode)->execute();
    }

    /**
     * Get all root nodes query builder
     *
     * @return Doctrine\ORM\QueryBuilder
     */
    public function getRootNodesQueryBuilder($sortByField = null, $direction = 'asc')
    {
        return $this->getChildrenQueryBuilder(null, true, $sortByField, $direction);
    }

    /**
     * Get all root nodes query
     *
     * @return Doctrine\ORM\Query
     */
    public function getRootNodesQuery($sortByField = null, $direction = 'asc')
    {
        return $this->getRootNodesQueryBuilder($sortByField, $direction)->getQuery();
    }

    /**
     * Get all root nodes
     *
     * @return array
     */
    public function getRootNodes($sortByField = null, $direction = 'asc')
    {
        return $this->getRootNodesQuery($sortByField, $direction)->execute();
    }

    /**
     * Get children from node
     *
     * @return Doctrine\ORM\QueryBuilder
     */
    public function getChildrenQueryBuilder($node = null, $direct = false, $sortByField = null, $direction = 'asc', $includeNode = false)
    {
        $meta = $this->getClassMetadata();
        $config = $this->listener->getConfiguration($this->_em, $meta->name);
        $separator = addcslashes($config['path_separator'], '%');
        $alias = 'materialized_path_entity';
        $path = $config['path'];
        $qb = $this->_em->createQueryBuilder($meta->name)
            ->select($alias)
            ->from($meta->name, $alias);
        $expr = '';

        if (is_object($node) && $node instanceof $meta->name) {
            $node = new EntityWrapper($node, $this->_em);
            $nodePath = $node->getPropertyValue($path);
            $expr = $qb->expr()->andx()->add(
                $qb->expr()->like($alias.'.'.$path, $qb->expr()->literal($nodePath.'%'))
            );

            if (!$includeNode) {
                $expr->add($qb->expr()->neq($alias.'.'.$path, $qb->expr()->literal($nodePath)));
            }

            if ($direct) {
                $expr->add(
                    $qb->expr()->not(
                        $qb->expr()->like($alias.'.'.$path, $qb->expr()->literal($nodePath.'%'.$separator.'%'.$separator))
                ));
            }
        } else if ($direct) {
            $expr = $qb->expr()->not(
                $qb->expr()->like($alias.'.'.$path, $qb->expr()->literal('%'.$separator.'%'.$separator.'%'))
            );
        }

        if ($expr) {
            $qb->where('('.$expr.')');
        }

        $orderByField = is_null($sortByField) ? $alias.'.'.$config['path'] : $alias.'.'.$sortByField;
        $orderByDir = $direction === 'asc' ? 'asc' : 'desc';
        $qb->orderBy($orderByField, $orderByDir);

        return $qb;
    }

    /**
     * Get children query
     *
     * @return Doctrine\ORM\Query
     */
    public function getChildrenQuery($node = null, $direct = false, $sortByField = null, $direction = 'asc', $includeNode = false)
    {
        return $this->getChildrenQueryBuilder($node, $direct, $sortByField, $direction, $includeNode)->getQuery();
    }

    /**
     * Get children
     *
     * @return array
     */
    public function getChildren($node = null, $direct = false, $sortByField = null, $direction = 'asc', $includeNode = false)
    {
        return $this->getChildrenQuery($node, $direct, $sortByField, $direction, $includeNode)->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function getNodesHierarchyQueryBuilder($node, $direct, array $config, array $options = array())
    {
        $sortBy = array(
            'field'     => null,
            'dir'       => 'asc'
        );

        if (isset($options['childSort'])) {
            $sortBy = array_merge($sortBy, $options['childSort']);
        }

        return $this->getChildrenQueryBuilder($node, $direct, $sortBy['field'], $sortBy['dir'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function getNodesHierarchyQuery($node, $direct, array $config, array $options = array())
    {
        return $this->getNodesHierarchyQueryBuilder($node, $direct, $config, $options)->getQuery();
    }

    /**
     * {@inheritdoc}
     */
    public function getNodesHierarchy($node, $direct, array $config, array $options = array())
    {
        return $this->getNodesHierarchyQuery($node, $direct, $config, $options)->getArrayResult();
    }

    /**
     * {@inheritdoc}
     */
    protected function validate()
    {
        return $this->listener->getStrategy($this->_em, $this->getClassMetadata()->name)->getName() === Strategy::MATERIALIZED_PATH;
    }
}