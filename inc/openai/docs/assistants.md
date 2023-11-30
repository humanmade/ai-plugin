# OpenAI Assistants API

The Assistants API built into WordPress providers a layer of abstraction over the [OpenAI Assistants API](https://platform.openai.com/docs/assistants/overview). You can use these APIs to create all kinds of assistants -- both for website users and website editors. The [Dashboard Assistant](../../dashboard-assistant/README.md) is built on this API, for example.

We recommend you familiarize yourself with the OpenAI Assistants API for a general understanding of the Assistants terminology and architecture.

## Creating an assistant

Assistants are stateful objects stored via the OpenAI API. Create a new assistant with:

```php
use AI\OpenAI;

$asssistant = OpenAI\Client::get_instance()->create_assistant(
	'gpt-4-1106-preview',
	'Shopping Helper',
	'A shopping assistant for WooCommerce sites',
	'You are a helpful shopping assistant for a fashion clothing ecommerce site. You can ask the shopper their preferences and hobbies to suggest useful products.',
);

update_option( 'ai_assistant_id', $asssistant->id );
```

You can also create assistants via the [OpenAI Playground](https://platform.openai.com/playground) and use `Client::get_assistant` to fetch it.

## Creating conversations with assistants

Each conversation is a "thread", and messages are appendedd to the thread. As the OpenAI Assistants API is stateful, there is a good amount of polling. See the [dashboard assistant REST API](../../dashboard-assistant/rest-api/namespace.php) for examples. As the asssistant responces are typically streaming, we use [SSE](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events/Using_server-sent_events) as the preferred technology to make conversations more realtime.

```php
use AI\OpenAI;

$client = OpenAI\Client::get_instance();
$thread = $client->create_thread();
$assistant_id = get_option( 'ai_assistant_id' );

// Update the user with the thread so they can continue conversations later
update_user_meta( wp_get_current_user_id(), 'ai_thread', $thread->id );

// Push a message to the thread from the user.
$message = $client->create_thread_message( new OpenAI\Thread_New_Message(
	role: 'user',
	thread_id: $thread->id,
	content: 'Hello',
) );

// Iterating run() will wait for all steps to be completed.
foreach ( $step as $thread->run( $assistant_id ) ) {
	// Step contains details about the next message the AI is processing. It can be writing a message,
	// use code_interpreter or running a custom function.
}

// Get all messages on the thread, including the AI's response.
$messages = $client->get_thread_messages( $thread->id );

print_r( $messages );
```

## Creating custom functions for the assistant to use

Probably the most useful feature of Assistants is the ability to provide custom functions to the AI assistant. Custom functions can be used to perform actions for the user iteracting with the assistant, or provide data fetch capabilities to the assistant.

This plugin provides a function calling abstraction, whereby first class PHP functions will automatically be converted and bridged to OpenAI Function Calling functions.

`OpenAI\Function_::from_callable` will take the function name, php documentation, arguments, types, descriptions and create an OpenAI function. The PHP function will automatically be called and data returned to the OpenAI thread when `Thread::run()` is iterated (see above.)


```php
use AI\OpenAI;

$client = OpenAI\Client::get_instance();
$assistant = $client->get_assistant( get_option( 'ai_assistant_id' ) );

// Add a new function for getting a user's order history. This allows a user interacting with
// the shopping assistant to ask "What were those Sunglasses I bought last year?"
$assistant->register_function( OpenAI\Function_::from_callable( get_shopping_history( ... ) ) );
// Add a function to allow the user to order an item. For example, continue from the previous
// message "ok, buy them please".
$assistant->register_function( OpenAI\Function_::from_callable( order_item( ... ) ) );
// Add a function to confirm the user. The order_item will present the order, but this function
// needs to be called to actually buy the order.
$assistant->register_function( OpenAI\Function_::from_callable( confirm_order_item( ... ) ) );
/**
 * Get the shopping order history for the current user.
 */
function get_shopping_history() : array {
	$orders = wc_get_orders( [
		'customer_id' => wp_get_current_user_id(),
	] );
	// You may want to only return a subset of data, or enrich it further.
	return $orders;
}

/**
 * Order an item for the user. This will create an order, which can be displayed to the user. Use confirm_order_item() to actually buy the item.
 */
function order_item( string $item_id ) : array {
	// Logic to create an order item, but don't actually purchase. We use confirm_order_item for that.
	return [
		'id' => '123123213',
		'items' => [],
		'price' => '$12',
	];
}

/**
 * Confirm an order, which will purchase the item and dispatch it to the user's shipping address.
 */
function confirm_order_item( string $order_id ) {
	// Confirm order.
}
```


