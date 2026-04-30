<?php declare(strict_types=1);

namespace SingleSignOn\Form\Element;

use Laminas\Form\Element\MultiCheckbox;

/**
 * MultiCheckbox that respects the 'required' attribute instead of forcing it to true.
 *
 * @see https://github.com/zendframework/zendframework/issues/2761#issuecomment-14488216
 */
class OptionalMultiCheckbox extends MultiCheckbox
{
    public function getInputSpecification(): array
    {
        $inputSpecification = parent::getInputSpecification();
        $inputSpecification['required'] = !empty($this->attributes['required']);
        return $inputSpecification;
    }
}
