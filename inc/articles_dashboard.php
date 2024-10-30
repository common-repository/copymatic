<?php
if(!defined('ABSPATH'))
	exit;
	$api_key = get_option('copymatic_apikey');
	$website_key = esc_attr(get_option('copymatic_website_key'));
?>
<div class="wrap">
	<input type="hidden" value="<?php echo esc_attr($api_key); ?>" id="api_key">
	<input type="hidden" value="load_articles" id="copymatic_action">
	<div class="copymatic-heading">
		<div>
			<h1 class="wp-heading-inline"><?php esc_html_e("My Copymatic Content", "copymatic"); ?></h1>
			<button type="button" class="page-title-action" id="refresh-articles"><?php esc_html_e("Refresh", "copymatic"); ?></button>
		</div>
		<?php if(empty($website_key)){ ?>
		<div>
			<p><?php esc_html_e("This website doesn't seem to be connected to your Copymatic account", "copymatic"); ?></p><button id="connect-website-copymatic" class="button button-primary"><?php esc_html_e("Connect Website", "copymatic"); ?></button>
		</div>
		<?php } ?>
	</div>
	<table class="widefat fixed striped copymatic-articles-table" cellspacing="0">
		<thead>
		<tr>
				<th id="columnname" class="manage-column column-columnname" scope="col"><?php esc_html_e("Blog Title", "copymatic"); ?></th>
				<th id="columnname" class="manage-column column-columdate word_count" scope="col" width="160px"><?php esc_html_e("Word Count", "copymatic"); ?></th>
				<th id="columnname" class="manage-column column-columdate date" scope="col" width="200px"><?php esc_html_e("Date", "copymatic"); ?></th>
				<th id="columnname" class="manage-column column-columnactions actions" scope="col"><?php esc_html_e("Actions", "copymatic"); ?></th>

		</tr>
		</thead>
		<tbody>
			<tr class="alternate">
				<td colspan="4"><div class="loading-row"><div class="lds-ellipsis"><div></div><div></div><div></div><div></div></div><?php esc_html_e("Loading your articles from Copymatic...", "copymatic"); ?></div></td>
			</tr>
		</tbody>	
		<tfoot>
		<tr>
				<th id="columnname" class="manage-column column-columnname" scope="col"><?php esc_html_e("Blog Title", "copymatic"); ?></th>
				<th id="columnname" class="manage-column column-columdate word_count" scope="col"><?php esc_html_e("Word Count", "copymatic"); ?></th>
				<th id="columnname" class="manage-column column-columdate date" scope="col"><?php esc_html_e("Date", "copymatic"); ?></th>
				<th id="columnname" class="manage-column column-columnactions actions" scope="col"><?php esc_html_e("Actions", "copymatic"); ?></th>
		</tr>
		</tfoot>
	</table>
	<div class="copymatic-explainer">
		<h2><?php esc_html_e("How it works", "copymatic"); ?></h2>
		<p><?php esc_html_e("This tool lets you import your blog posts created on Copymatic in one click.", "copymatic"); ?></p>
		<ul>
			<li><strong><?php esc_html_e("Import:", "copymatic"); ?></strong> <?php esc_html_e("Will create your Copymatic article as a draft, ready to be edited or published.", "copymatic"); ?></li>
			<li><strong><?php esc_html_e("Edit in Copymatic:", "copymatic"); ?></strong> <?php esc_html_e("Will let you edit the article in Copymatic so you can generate more content or let the AI complete your text (Write more function).", "copymatic"); ?></li>
			<li><strong><?php esc_html_e("Delete:", "copymatic"); ?></strong> <?php esc_html_e("Will delete your article from Copymatic, not from your Wordpress website.", "copymatic"); ?></li>
		</ul>
	</div>
</div>
<script>
	const api_key = '<?php echo esc_js($api_key); ?>';
</script>