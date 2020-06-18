<?php

namespace Fesor\RequestObject;

use Symfony\Component\Validator\Constraint;

class RequestObject
{
    private $payload;

    public function setPayload(array $payload = [])
    {
        $this->payload = $payload;
    }

    /**
     * @return Constraint|Constraint[]
     */
    public function rules()
    {
    }

    /**
     * @return array|void
     */
    public function validationGroup(array $payload)
    {
    }

    /**
     * @param string     $name
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function get($name, $default = null)
    {
        return $this->has($name) ?
            $this->payload[$name] : $default;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->payload);
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->payload;
    }
}
