<h2>Test</h2>
{if $status == 'ok'}
	<p>{l s='Your order on' mod='charityclear'} <span class="bold">{$shop_name}</span> {l s='is complete.' mod='charityclear'}
		<br /><br /><span class="bold">{l s='Your order will be sent as soon as possible.' mod='charityclear'}</span>
		<br /><br />{l s='For any questions or for further information, please contact our' mod='charityclear'} <a href="{$base_dir_ssl}contact-form.php">{l s='customer support' mod='charityclear'}</a>.
	</p>
{else}
	<p class="warning">
		Unfortunately payment has failed for your order. Please recomplete the checkout process.
	</p>
{/if}
