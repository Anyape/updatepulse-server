<?php
/**
 * Plugin help page template.
 *
 * @package UpdatePulse_Server
 *
 * @var string $header       Rendered admin settings header.
 * @var string $packages_dir Package storage directory.
 * @var array  $options      Plugin options used by the help content.
 * @var string $mu_path      Must-use plugin directory path.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<div class="wrap upserv-wrap">
	<?php echo $header ? wp_kses_post( $header ) : ''; ?>
	<?php if ( $options['use_vcs'] ) : ?>
	<div class="help-content">
		<h2><?php esc_html_e( 'Registering packages with a Version Control System', 'updatepulse-server' ); ?></h2>
		<p>
			<?php esc_html_e( 'It is necessary to register packages associated with a Version Control System for them to be available in UpdatePulse Server with one of the following methods:', 'updatepulse-server' ); ?>
		</p>
		<ul class="description">
			<li>
				<?php
					printf(
						// translators: %1$s is <strong>Register a package using a VCS</strong>, %2$s is <a href="admin.php?page=upserv-page">Packages Overview</a>
						esc_html__( '[simple] using the %1$s feature in %2$s', 'updatepulse-server' ),
						'<strong>' . esc_html__( 'Register a package using a VCS', 'updatepulse-server' ) . '</strong>',
						'<a href="' . esc_url( admin_url( 'admin.php?page=upserv-page' ) ) . '">' . esc_html__( 'Packages Overview', 'updatepulse-server' ) . '</a>'
					);
				?>
			</li>
			<li>
				<?php
					esc_html_e( '[simple] triggering a webhook from a VCS already added to UpdatePulse Server', 'updatepulse-server' );
				?>
				<br>
				<?php
				printf(
					// translators: %1$s is the webhook URL, %2$s is <code>package-type</code>, %3$s is <code>plugin</code>, %4$s is <code>theme</code>, %5$s is <code>generic</code>, %6$s is <code>package-slug</code>
					esc_html__( 'Webhook URL: %1$s - where %2$s is the package type ( %3$s or %4$s or %5$s ) and %6$s is the slug of the package to register.', 'updatepulse-server' ),
					'<code>' . esc_url( home_url( '/updatepulse-server-webhook/package-type/package-slug' ) ) . '</code>',
					'<code>package-type</code>',
					'<code>plugin</code>',
					'<code>theme</code>',
					'<code>generic</code>',
					'<code>package-slug</code>'
				);
				?>
			</li>
			<li>
				<?php
					printf(
						// translators: %s is a wp updatepulse-server download_remote_package command.
						esc_html__( '[advanced] calling %s in the command line, with the VCS-related parameters corresponding to a VCS configuration saved in UpdatePulse Server', 'updatepulse-server' ),
						'<code>' . esc_html( 'wp updatepulse-server download_remote_package <package-slug> <plugin|theme|generic> <vcs-url> <branch>' ) . '</code>'
					);
				?>
			</li>
			<li>
				<?php
					printf(
						// translators: %s is <code>upserv_download_remote_package( string $package_slug, string $type );</code>
						esc_html__( '[expert] calling the %s method in your own code, with the VCS-related parameters corresponding to a VCS configuration saved in UpdatePulse Server', 'updatepulse-server' ),
						'<code>upserv_download_remote_package( string $package_slug, string $type, string $vcs_url = false, string branch = \'main\' );</code>'
					);
				?>
			</li>
			<li>
				<?php
					printf(
						// translators: %s is <code>add</code>
						esc_html__( '[expert] calling the %s method of the package API, with the VCS-related parameters corresponding to a VCS configuration saved in UpdatePulse Server present in the request payload', 'updatepulse-server' ),
						'<code>add</code>'
					);
				?>
			</li>
		</ul>
		<hr>
	<?php endif; ?>
		<h2><?php esc_html_e( 'Providing updates - packages requirements', 'updatepulse-server' ); ?></h2>
		<p>
			<?php esc_html_e( 'To link your packages to UpdatePulse Server, and optionally to prevent users from getting updates of your packages without a license, your packages need to include some extra code.', 'updatepulse-server' ); ?><br><br>
			<?php esc_html_e( 'For plugins, and themes, it is fairly straightforward:', 'updatepulse-server' ); ?>
		</p>
		<ul>
			<li>
			<?php
			printf(
				// translators: %1$s is <code>lib</code>, %2$s is <code>plugin-update-checker</code>, %3$s is <code>updatepulse-updater</code>, %4$s is <code>dummy-[plugin|theme]</code>, %5$s is "in the UpdatePulse Server Integration"
				esc_html__( 'Add a %1$s directory with the %2$s and %3$s libraries to the root of the package as provided in %4$s of the %5$s repository.', 'updatepulse-server' ),
				'<code>lib</code>',
				'<code>plugin-update-checker</code>',
				'<code>updatepulse-updater</code>',
				'<code>dummy-[plugin|theme]</code>',
				'<a target="_blank" href="https://github.com/Anyape/updatepulse-server-integration">' . esc_html__( 'UpdatePulse Server Integration', 'updatepulse-server' ) . '</a>'
			);
			?>
			</li>
			<li>
				<?php
				printf(
					// translators: %s is <code>functions.php</code>
					esc_html__( 'Add the following code to the main plugin file or to the theme\'s %s file:', 'updatepulse-server' ),
					'<code>functions.php</code>'
				);
				?>
				<br>
<pre>/** Enable updates
 * Replace `$prefix_` in `$prefix_updater` variable to a unique string for your package.
 * Replace vX_X with the version of the UpdatePulse Updater you are using.
 **/
use Anyape\UpdatePulse\Updater\vX_X\UpdatePulse_Updater;
require_once __DIR__ . '/lib/updatepulse-updater/class-updatepulse-updater.php';

$prefix_updater = new UpdatePulse_Updater(
	wp_normalize_path( __FILE__ ),
	0 === strpos( __DIR__, WP_PLUGIN_DIR ) ? wp_normalize_path( __DIR__ ) : get_stylesheet_directory()
);
</pre>
			</li>
			<li>
				<?php
				printf(
					// translators: %1s is <code>style.css</code>
					esc_html__( 'Optionally add headers to the main plugin file or to the theme\'s %s file to enable license checks:', 'updatepulse-server' ),
					'<code>style.css</code>'
				);
				?>
				<br>
				<pre>Require License: yes
Licensed With: another-plugin-or-theme-slug</pre><br>
				<?php
				printf(
					// translators: %1$s is <code>yes</code>, %2$s is <code>true</code>, %3$s is <code>1</code>
					esc_html__( 'The "Require License" header can be %1$s, %2$s, or %3$s: all other values are considered as false; it is used to enable license checks for your package.', 'updatepulse-server' ),
					'<code>yes</code>',
					'<code>true</code>',
					'<code>1</code>'
				);
				?>
				<br>
				<?php
				printf(
					// translators: %s is <code>another-plugin-or-theme-slug</code>
					esc_html__( 'The "Licensed With" header is used to link packages together (for example, in the case of an extension to a main plugin the user already has a license for, if this header is present in the extension, the license check is made against the main plugin). It must be the slug of another plugin or theme that is already present in your UpdatePulse Server.', 'updatepulse-server' ),
					'<code>another-plugin-or-theme-slug</code>'
				);
				?>
			</li>
			<li>
				<?php
				printf(
					// translators: %1$s is <code>updatepulse.json</code>, %2$s is <code>"server"</code>
					esc_html__( 'Add a %1$s file at the root of the package with the following content - change the value of %2$s to your own (required):', 'updatepulse-server' ),
					'<code>updatepulse.json</code>',
					'<code>"server"</code>'
				);
				?>
				<br>
				<pre>{
	"server": "https://server.domain.tld/"
}</pre>
				</li>
				<li>
				<?php esc_html_e( 'Connect UpdatePulse Server with your repository and register your package, or manually upload your package to UpdatePulse Server.', 'updatepulse-server' ); ?>
			</li>
		</ul>
		<p>
			<?php
			esc_html_e( 'For generic packages, the steps involved entirely depend on the language used to write the package and the update process of the target platform.', 'updatepulse-server' );
			?>
			<br>
			<?php
			printf(
				// translators: %s is a link to the documentation
				esc_html__( 'You may refer to the documentation found %s.', 'updatepulse-server' ),
				'<a target="_blank" href="' . esc_url( 'https://github.com/anyape/updatepulse-server/blob/main/docs/generic.md' ) . '">' . esc_html__( 'here', 'updatepulse-server' ) . '</a>'
			);
			?>
		</p>
		<hr>
		<h2><?php esc_html_e( 'Integration examples', 'updatepulse-server' ); ?></h2>
		<p>
			<?php
			printf(
				// translators: %1$s is the link to the UpdatePulse Server Integration repository
				esc_html__( 'Dummy packages are available in the %1$s repository.', 'updatepulse-server' ),
				'<a target="_blank" href="https://github.com/Anyape/updatepulse-server-integration">' . esc_html__( 'UpdatePulse Server Integration', 'updatepulse-server' ) . '</a>'
			);
			?>
		<p>
			<?php
			printf(
				// translators: %1$s is <code>dummy-plugin</code>, %2$s is <code>dummy-theme</code>
				esc_html__( 'See %1$s for an example of plugin, and %2$s for an example of theme. They are fully functionnal and can be used to test all the features of the server with a test client installation of WordPress.', 'updatepulse-server' ),
				'<code>dummy-theme</code>',
				'<code>dummy-plugin</code>',
			);
			?>
		</p>
		<p>
			<?php
			printf(
				// translators: %1$s is <code>dummy-generic</code>, %2$s is `updatepulse-api.[sh|php|js|py]`
				esc_html__( 'See %1$s for examples of a generic package written in Bash, NodeJS, PHP with Curl, and Python. The API calls made by generic packages to the license API and Update API are the same as those made by the WordPress packages. Unlike the upgrade library provided with plugins & themes, the code found in %2$s files is NOT ready for production environment and MUST be adapted.', 'updatepulse-server' ),
				'<code>dummy-generic</code>',
				'<code>updatepulse-api.[sh|php|js|py]</code>'
			);
			?>
		</p>
		<p>
			<?php
			printf(
				// translators: %1$s is <code>packages_dir</code>, %2$s is <code>package-slug.zip</code>, %3$s is <code>package-slug.php</code>
				esc_html__( 'Unless "Enable VCS" is checked in "Version Control Systems ", you need to manually upload the packages zip archives (and subsequent updates) in %1$s. A package needs to be a valid generic package, or a valid WordPress plugin or theme package, and in the case of a plugin the main plugin file must have the same name as the zip archive. For example, the main plugin file in %2$s would be %3$s.', 'updatepulse-server' ),
				'<code>' . esc_html( $packages_dir ) . '</code>',
				'<code>package-slug.zip</code>',
				'<code>package-slug.php</code>',
			);
			?>
		</p>
		<hr>
		<h2><?php esc_html_e( 'Scheduled tasks optimisation', 'updatepulse-server' ); ?></h2>
		<p>
			<?php esc_html_e( 'By default, scheduled tasks are handled by the WordPress cron system. This means that the tasks are executed when a visitor accesses the site, which leads to delays in the execution of the tasks.', 'updatepulse-server' ); ?>
			<br>
			<?php esc_html_e( 'This is clearly suboptimal: the website where UpdatePulse Server is installed is very likely not meant to be visited by users, and the tasks should be executed as close to the scheduled time as possible.', 'updatepulse-server' ); ?>
		<p>
			<?php
			printf(
				// translators: %s is a link to the documentation
				esc_html__( 'To make sure that the tasks are executed on time, it is recommended to set up a true cron job by %s.', 'updatepulse-server' ),
				'<a target="_blank" href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/">' . esc_html__( 'hooking WP-Cron Into the System Task Scheduler', 'updatepulse-server' ) . '</a>'
			);
			?>
		</p>
		<p>
			<?php
			printf(
				// translators: %s is a link to the Action Scheduler plugin
				esc_html__( 'For more advanced scheduling, it is recommended to use the %s plugin.', 'updatepulse-server' ),
				'<a target="_blank" href="https://wordpress.org/plugins/action-scheduler/">' . esc_html__( 'Action Scheduler', 'updatepulse-server' ) . '</a>'
			);
			?>
			<br>
			<?php esc_html_e( 'Simply install and activate the plugin, and UpdatePulse Server will automatically use it to schedule tasks instead of the default core scheduler.', 'updatepulse-server' ); ?>
		</p>
		<hr>
		<h2><?php esc_html_e( 'Requests optimisation', 'updatepulse-server' ); ?></h2>
		<p>
			<?php esc_html_e( 'When the remote clients where plugins, themes, or generic packages are installed send a request to check for updates, download a package or check or change license status, WordPress where UpdatePulse Server is installed is also loaded, with its own plugins and themes.', 'updatepulse-server' ); ?>
			<br>
			<?php esc_html_e( 'This is suboptimal: the request should be handled as quickly as possible, and the WordPress core should be loaded as little as possible.', 'updatepulse-server' ); ?>
		</p>
		<p>
			<?php
			printf(
				// translators: %1$s is <code>optimisation/upserv-default-optimizer.php</code>, %2$s is the MU Plugin's path
				esc_html__( 'To solve this, the Must-Use Plugin file %1$s automatically copied to %2$s upon activating UpdatePulse Server.', 'updatepulse-server' ),
				'<code>upserv-default-optimizer.php</code>',
				'<code>' . esc_html( $mu_path ) . 'upserv-default-optimizer.php</code>',
			);
			?>
			<br>
			<?php esc_html_e( 'It runs before everything else, and offers mechanisms to prevent WordPress core from executing beyond what is strictly necessary.', 'updatepulse-server' ); ?>
			<br>
			<?php esc_html_e( 'This file has no effect on other plugins activation status, has no effect when UpdatePulse is deactivated, and is automatically deleted when UpdatePulse Server is uninstalled.', 'updatepulse-server' ); ?>
		</p>
		<p>
			<?php
			printf(
				// translators: %1$s is the link to the UpdatePulse Server Integration repository
				esc_html__( 'Aside from the default optimizer, the %1$s repository contains other production-ready Must-Use Plugins developers can download and add to their UpdatePulse Server installation.', 'updatepulse-server' ),
				'<a target="_blank" href="https://github.com/Anyape/updatepulse-server-integration">' . esc_html__( 'UpdatePulse Server Integration', 'updatepulse-server' ) . '</a>'
			);
			?>
			<br>
			<?php esc_html_e( 'Contributions via pull requests are welcome.', 'updatepulse-server' ); ?>
		</p>
		<hr>
		<h2><?php esc_html_e( 'More help...', 'updatepulse-server' ); ?></h2>
		<p>
			<?php
			printf(
				// translators: %s is a link to the documentation
				esc_html__( 'The full documentation can be found %s, with more details for developers on how to integrate UpdatePulse Server with their own plugins, themes, and generic packages.', 'updatepulse-server' ),
				'<a target="_blank" href="https://github.com/anyape/updatepulse-server/blob/main/README.md">' . esc_html__( 'here', 'updatepulse-server' ) . '</a>',
			);
			?>
		</p>
		<p>
			<?php
			printf(
				// translators: %1$s is a link to opening an issue, %2$s is a contact email
				esc_html__( 'After reading the documentation, for more help on how to use UpdatePulse Server, please %1$s - bugfixes are welcome via pull requests, detailed bug reports with accurate pointers as to where and how they occur in the code will be addressed in a timely manner, and a fee will apply for any other request (if they are addressed). If and only if you found a security issue, please contact %2$s with full details for responsible disclosure.', 'updatepulse-server' ),
				'<a target="_blank" href="https://github.com/anyape/updatepulse-server/issues">' . esc_html__( 'open an issue on Github', 'updatepulse-server' ) . '</a>',
				'<a href="mailto:updatepulse@anyape.com">updatepulse@anyape.com</a>',
			);
			?>
		</p>
	</div>
</div>
