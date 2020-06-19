Symfony Request Objects
===========================

[![Build Status](https://travis-ci.org/fesor/request-objects.svg?branch=master)](https://travis-ci.org/fesor/request-objects)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/fesor/request-objects/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/fesor/request-objects/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/fesor/request-objects/v/stable)](https://packagist.org/packages/fesor/request-objects)
[![Total Downloads](https://poser.pugx.org/fesor/request-objects/downloads)](https://packagist.org/packages/fesor/request-objects)
[![License](https://poser.pugx.org/fesor/request-objects/license)](https://packagist.org/packages/fesor/request-objects)

**Note**: This library is taken from this library [fesor/request-objects](https://github.com/fesor/request-objects) but this library is working on symfony v5+ . with a few updates

## Why?

Symfony Forms component is a very powerful tool for handling forms. But nowadays things have changed.
Complex forms are handled mostly on the client side. As for simple forms `symfony/forms` has very large overhead.

And in some cases you just don't have forms. For example, if you are developing an HTTP API, you probably just
need to interact with request payload. So why not just wrap request payload in some user defined object and
validate just it? This also encourages separation of concerns and will help you in case of API versioning.

## Usage

First of all, we need to install this package via composer:

```
composer require geeky/request-objects
```

And register the bundle:

```
    public function registerBundles()
    {
        $bundles = [
            // ...
            new \Fesor\RequestObject\Bundle\RequestObjectBundle(),
        ];
    }
```

Bundle doesn't require any additional configuration, but you could also specify an error response
provider service in bundle config. We will come back to this in "Handle validation errors" section.

### Define your request objects

All user defined requests should extend `Fesor\RequestObject\RequestObject`. Let's create a simple
request object for user registration action:

```php
use Fesor\RequestObject\RequestObject;
use Symfony\Component\Validator\Constraints as Assert;

class RegisterUserRequest extends RequestObject
{
    public function rules()
    {
        return new Assert\Collection([
            'email' => new Assert\Email(['message' => 'Please fill in valid email']),
            'password' => new Assert\Length(['min' => 4, 'minMessage' => 'Password is to short']),
            'first_name' => new Assert\NotNull(['message' => 'Please provide your first name']),
            'last_name' => new Assert\NotNull(['message' => 'Please provide your last name'])
        ]);
    }
}
```

After that we can just use it in our action:

```php
public function registerUserAction(RegisterUserRequest $request)
{
    // Do Stuff! Data is already validated!
}
```

This bundle will bind validated request object to the `$request` argument. Request object has very simple interface
 for data interaction. It is very similar to Symfony's request object but is considered immutable by default (although you
 can add some setters if you wish so)

```php
// returns value from payload by specific key or default value if provided
$request->get('key', 'default value');

// returns whole payload
$request->all();
```

### Where payload comes from?

This library has default implementation of `PayloadResolver` interface, which acts this way:

1) If a request can have a body (i.e. it is POST, PUT, PATCH or whatever request with body)
it uses union of `$request->request->all()` and `$request->files->all()` arrays as payload.

2) If request can't have a body (i.e. GET, HEAD verbs), then it uses `$request->query->all()`.

If you wish to apply custom logic for payload extraction, you could implement `PayloadResolver` interface within
your request object:

```php
class CustomizedPayloadRequest extends RequestObject implements PayloadResolver
{
    public function resolvePayload(Request $request)
    {
        $query = $request->query->all();
        // turn string to array of relations
        if (isset($query['includes'])) {
            $query['includes'] = explode(',', $query['includes']);
        }

        return $query;
    }
}
```

This will allow you to do some crazy stuff with your requests and DRY a lot of stuff.


### Validating payload

As you can see from previous example, the `rules` method should return validation rules for [symfony validator](http://symfony.com/doc/current/book/validation.html).
Your request payload will be validated against it and you will get valid data in your action.

If you have some validation rules which depend on payload data, then you can handle it via validation groups.

**Please note**: due to limitations in `Collection` constraint validator it is not so handy to use groups.
 So instead it is recommended to use `Callback` validator in tricky cases with dependencies on payload data.
 See [example](examples/Request/ContextDependingRequest.php) for details about problem.

You may provide validation group by implementing `validationGroup` method:

```php
public function validationGroup(array $payload)
{
    return isset($payload['context']) ?
        ['Default', $payload['context']] : null;
}
```

### Handling validation errors

If validated data is invalid, library will throw exception which will contain validation errors and request object.

But if you don't want to handle it via `kernel.exception` listener, you have several options.

First is to use your controller action to handle errors:

```php

public function registerUserAction(RegisterUserRequest $request, ConstraintViolationList $errors)
{
    if (0 !== count($errors)) {
        // handle errors
    }
}

```

But this not so handy and will break DRY if you just need to return common error response. Thats why
library provides you `ErrorResponseProvider` interface. You can implement it in your request object and move this
code to `getErrorResponse` method:

```php
public function getErrorResponse(ConstraintViolationListInterface $errors)
{
    return new JsonResponse([
        'message' => 'Please check your data',
        'errors' => array_map(function (ConstraintViolation $violation) {

            return [
                'path' => $violation->getPropertyPath(),
                'message' => $violation->getMessage()
            ];
        }, iterator_to_array($errors))
    ], 400);
}
```
## Exception Listner Handling

If you need to handle the validaion exception from the exception itself you can take a look at this example [Excetion Example](https://github.com/TheGeekyM/symfony-object-request-validation/blob/master/examples/src/EventListener/ExceptionListener.php)

So the first thing you should create the `src/EventListener/ExceptionListener.php` file or anywhere you want then paste the example file in it and you can customize the response as you want,

secondly you must register the created file into `services.yaml` file like this:

```
parameters:

services:
    App\EventListener\ExceptionListener: #here you can create your file anywhere else
            tags:
                - { name: kernel.event_listener, event: kernel.exception }
```

## More examples

If you're still not sure is it useful for you, please see the `examples` directory for more use cases.
Didn't find your case? Then share your use case in issues!

## Contribution

Feel free to give feedback and feature requests or post issues. PR's are welcomed!
