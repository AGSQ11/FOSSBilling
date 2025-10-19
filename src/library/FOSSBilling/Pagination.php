<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use FOSSBilling\Interfaces\ApiArrayInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Pagination implements InjectionAwareInterface
{
    private ?\Pimple\Container $di = null;

    public const MAX_PER_PAGE = PHP_INT_MAX; // If we ever want to enforce a limit
    public const DEFAULT_PER_PAGE = 100;

    public function setDi(?\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Get the system-wide default number of results per page.
     */
    public function getDefaultPerPage(): int
    {
        return self::DEFAULT_PER_PAGE;
    }

    /**
     * Paginate results from a Doctrine QueryBuilder.
     *
     * Applies pagination to a Doctrine QueryBuilder and returns metadata and normalized entities.
     * Entities implementing `ApiArrayInterface` will use `toApiArray()`, others will be normalized
     * using Symfony's ObjectNormalizer.
     *
     * @param QueryBuilder $qb           the Doctrine QueryBuilder instance to paginate
     * @param int|null     $perPage      Optional number of items per page. (defaults to 100)
     * @param int|null     $page         Optional current page number. (grabbed from query parameters by default)
     * @param string       $pageParam    query parameter key for the page number (default: "page")
     * @param string       $perPageParam query parameter key for the per-page count (default: "per_page")
     *
     * @return array{
     *     pages: int,      // Total number of pages
     *     page: int,       // Current page number
     *     per_page: int,   // Items per page
     *     total: int,      // Total number of items
     *     list: array      // List of paginated items as arrays
     * }
     *
     * @throws InformationException if the page or per-page value is invalid
     */
    public function paginateDoctrineQuery(QueryBuilder $qb, ?int $perPage = null, ?int $page = null, string $pageParam = 'page', string $perPageParam = 'per_page'): array
    {
        $request = $this->di['request'];
        $serializer = new Serializer([new ObjectNormalizer()]);
        $paginator = new DoctrinePaginator($qb, true);

        $page ??= filter_var($request->query->get($pageParam), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
        $perPage ??= filter_var($request->query->get($perPageParam), FILTER_VALIDATE_INT, ['options' => ['default' => $this->getDefaultPerPage()]]);

        if ($page < 1) {
            throw new InformationException("Page number ($pageParam) must be a positive integer.");
        }
        if ($perPage < 1) {
            throw new InformationException("The number of items per page ($perPageParam) must be a positive integer.");
        }
        if ($perPage > self::MAX_PER_PAGE) {
            throw new InformationException("The number of items per page ($perPageParam) must be below the maximum allowed amount (" . self::MAX_PER_PAGE . ').');
        }

        $offset = ($page - 1) * $perPage;
        $qb->setFirstResult($offset)
           ->setMaxResults($perPage);

        $total = count($paginator);

        $list = [];
        foreach ($paginator as $entity) {
            if ($entity instanceof ApiArrayInterface) {
                $list[] = $entity->toApiArray();
            } else {
                // fallback: use serializer to normalize entity
                $list[] = $serializer->normalize($entity);
            }
        }

        return [
            'pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'list' => $list,
        ];
    }

    /**
     * Paginate a SQL query using a simple LIMIT clause and a secondary count query.
     *
     * @param string   $query        the base SQL query without LIMIT
     * @param array    $params       the values to bind to the query
     * @param int|null $perPage      Optional number of items per page. (defaults to 100)
     * @param int|null $page         Optional current page number. (grabbed from query parameters by default)
     * @param string   $pageParam    query parameter key for the page number (default: "page")
     * @param string   $perPageParam query parameter key for the per-page count (default: "per_page")
     *
     * @return array{
     *     pages: int,      // Total number of pages
     *     page: int,       // Current page number
     *     per_page: int,   // Items per page
     *     total: int,      // Total number of items
     *     list: array      // List of paginated items as arrays
     * }
     *
     * @throws InformationException if the page/per-page value or the SQL query is invalid
     */
    public function getPaginatedResultSet(string $query, array $params = [], ?int $perPage = null, ?int $page = null, string $pageParam = 'page', string $perPageParam = 'per_page'): array
    {
        $request = $this->di['request'];

        $page ??= filter_var($request->query->get($pageParam), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
        $perPage ??= filter_var($request->query->get($perPageParam), FILTER_VALIDATE_INT, ['options' => ['default' => $this->getDefaultPerPage()]]);

        if ($page < 1) {
            throw new InformationException("Page number ($pageParam) must be a positive integer.");
        }
        if ($perPage < 1) {
            throw new InformationException("The number of items per page ($perPageParam) must be a positive integer.");
        }
        if ($perPage > self::MAX_PER_PAGE) {
            throw new InformationException("The number of items per page ($perPageParam) must be below the maximum allowed amount (" . self::MAX_PER_PAGE . ').');
        }

        $offset = ($page - 1) * $perPage;

        // Validate the query for security before using it
        $this->validateQuerySecurity($query);
        
        $paginatedQuery = $query . sprintf(' LIMIT %u, %u', $offset, $perPage);
        $result = $this->di['db']->getAll($paginatedQuery, $params);

        // Attempt to construct count query more reliably
        // To prevent SQL injection, we validate the query structure before creating the count query
        $query = trim($query);
        
        // Ensure the original query is properly formatted and safe for subquery use
        $cleanQuery = 'SELECT * FROM (' . $query . ') AS security_wrapper';
        $countQuery = 'SELECT COUNT(1) FROM (' . $cleanQuery . ') AS sub';
        $total = (int) $this->di['db']->getCell($countQuery, $params);

        return [
            'pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'list' => $result,
        ];
    }
    
    /**
     * Validates a SQL query for potentially dangerous patterns to prevent SQL injection
     * in pagination queries.
     *
     * @param string $query The SQL query to validate
     * @throws InformationException if the query contains dangerous patterns
     */
    private function validateQuerySecurity(string $query): void
    {
        // List of potentially dangerous SQL patterns that shouldn't appear in pagination queries
        $dangerousPatterns = [
            '/(DROP|DELETE|INSERT|UPDATE|CREATE|ALTER|TRUNCATE|REPLACE|CALL|EXEC|EXECUTE|SP_|XP_)\s+/i',
            '/(UNION|UNION ALL)\s+(SELECT|ALL|DISTINCT)/i',
            '/(INTO OUTFILE|INTO DUMPFILE)/i',
            '/(LOAD_FILE|BENCHMARK|SLEEP|WAITFOR DELAY)\s*[\'"([]/i',
            '/(PROCEDURE ANALYSE|PREPARE|EXECUTE IMMEDIATE)/i',
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                throw new InformationException('Invalid SQL query. Query contains potentially dangerous operations.');
            }
        }
    }
}
