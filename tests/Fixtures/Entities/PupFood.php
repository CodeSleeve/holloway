<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Entities;

class PupFood extends Entity
{
    use HasTimestamps;
    
    protected string $name;
    protected ?Company $company;
    protected int $company_id;

    /**
     * @param Company $company
     * @param string $name
     */
    public function __construct(Company $company, string $name)
    {
        $this->setCompany($company);
        $this->name = $name;
    }

    /**
     * @param string $name
     * @return void
     */
    public function setNam(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param Company $company
     */
    public function setCompany(Company $company)
    {
        $this->company = $company;
        $this->company_id = $company->id;
    }
}