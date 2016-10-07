<?php get_header(); ?>
<br />
<form id="webmention-form" action="<?php echo get_webmention_endpoint(); ?>" method="post">
<p>
	<label for="webmention-source"><?php _e( 'Source URL:', 'linkbacks' ); ?></label>
	<input id="webmention-source" size="15" type="url" name="source" placeholder="Where Did You Link to?" />
</p>
<p>
<label for="webmention-target"><?php _e( 'Target URL(must be on this site):', 'linkbacks' ); ?></label>
<input id="webmention-target" size="15" type="url" name="target" placeholder="What Did You Link to?" />
<br /><br/>
<input id="webmention-submit" type="submit" name="submit" value="Send" />
</p>
</form>
<p><?php _e( 'Webmention is a way for you to tell me "Hey, I have written a response to your
		post."', 'linkbacks' ); ?> </p>
<p><?php _e( 'Learn more about webmentions at <a href="http://webmention.net">webmention.net</a>',
		'linkbacks' ); ?> </p>

<?php get_footer(); ?>
