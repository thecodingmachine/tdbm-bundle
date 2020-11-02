<?php


namespace TheCodingMachine\TDBM\Bundle\Tests\Fixtures;


use TheCodingMachine\TDBM\Bundle\Tests\GeneratedDb1\Daos\CountryDao;
use TheCodingMachine\TDBM\Bundle\Tests\GeneratedDb2\Daos\PersonDao;

/**
 * A dirty hack to access CountryDao and PersonDao that are private services.
 * This service is public.
 */
class PublicService
{
    /**
     * @var CountryDao|null
     */
    private $countryDao;
    /**
     * @var PersonDao|null
     */
    private $personDao;

    public function __construct(?CountryDao $countryDao = null, ?PersonDao $personDao = null)
    {
        $this->countryDao = $countryDao;
        $this->personDao = $personDao;
    }

    /**
     * @return CountryDao|null
     */
    public function getCountryDao(): ?CountryDao
    {
        return $this->countryDao;
    }

    /**
     * @return PersonDao|null
     */
    public function getPersonDao(): ?PersonDao
    {
        return $this->personDao;
    }
}
