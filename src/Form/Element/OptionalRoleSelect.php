<?php declare(strict_types=1);

namespace SingleSignOn\Form\Element;

use Laminas\Form\Element\Select;

/**
 * Select populated with Omeka roles, respecting the 'required' attribute.
 *
 * @see https://github.com/zendframework/zendframework/issues/2761#issuecomment-14488216
 */
class OptionalRoleSelect extends Select
{
    public function getInputSpecification(): array
    {
        $inputSpecification = parent::getInputSpecification();
        $inputSpecification['required'] = !empty($this->attributes['required']);
        return $inputSpecification;
    }
}
