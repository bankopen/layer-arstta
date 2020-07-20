<?php if(!empty($error)) { echo $error; } else {?>

<form action='<?php echo $surl; ?>' method='post' style='display: none' name='layer_payment_int_form'>
	<input type='hidden' name='layer_pay_token_id' value='<?php echo $payment_token_id; ?>'>
	<input type='hidden' name='woo_order_id' value='<?php echo $woo_order_id; ?>'>
	<input type='hidden' name='layer_order_amount' value='<?php echo $payment_token_amount; ?>'>
    <input type='hidden' id='layer_payment_id' name='layer_payment_id' value=''>
    <input type='hidden' id='fallback_url' name='fallback_url' value=''>
    <input type='hidden' name='hash' value='<?php echo $hash; ?>'>
</form>
<div class='buttons'>
	<div class='pull-right'>
		<input type='submit' value='<?php echo $button_confirm; ?>' class='btn btn-primary' onclick='triggerLayer(); return false;' />
	</div>
</div>

<script type="text/javascript">
	var script = document.createElement('script');
	script.setAttribute('src', '<?php echo $remote_script; ?>');
	document.body.appendChild(script);
	
	function triggerLayer() {							 							
		Layer.checkout(
		{
			token: '<?php echo $payment_token_id; ?>',
			accesskey: '<?php echo $apikey; ?>'
		},
		function (response) {
			console.log(response)
			if(response !== null || response.length > 0 ){
				if(response.payment_id !== undefined){
					document.getElementById('layer_payment_id').value = response.payment_id;
				}
			}
			document.layer_payment_int_form.submit();
		},
		function (err) {
			//alert(err.message);
		});	
	}
</script>
 <?php } ?>