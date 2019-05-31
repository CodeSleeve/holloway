<?php

namespace CodeSleeve\Tests\Holloway\Fixtures\Entities;

class PupFood extends Entity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var Company
     */
    protected $company;

    /**
     * @param int    $id
     * @param int    $companyId
     * @param string $name
     */
    public function __construct(int $id, int $companyId, string $name)
    {
        $this->id = $id;
        $this->companyId = $companyId;
        $this->name = $name;
    }

    /**
     * @param iterable $relationships
     */
    public function setRelationships(iterable $relationships)
    {
        if (isset($relationships['company'])) {
            $this->setCompany($relationships['company']);
        }
    }

    /**
     * @return int
     */
    public function id() : int
    {
        return $this->id;
    }

    /**
     * @return companyId
     */
    public function companyId() : int
    {
        return $this->companyId;
    }

    /**
     * @param int $value
     * @return void
     */
    public function setId(int $value) : void
    {
        $this->id = $value;
    }

    /**
     * @return string
     */
    public function name() : string
    {
        return $this->name;
    }

    /**
     * @return Company
     */
    public function company() : company
    {
        return $this->company;
    }

    /**
     * @param Company $company
     */
    public function setCompany(Company $company)
    {
        $this->company = $company;
    }
}