<h2 style="margin-bottom: 0;">Areas</h2>
<p style="font-style: italic; margin-top: 5px; line-height: 1.2em;">Areas are fields which serve as "slots" for blocks.<br>When you assign an area field to a template, site editors will be able to add whatever blocks they want to it in any quantity and in any order.</p>
<?php if (empty($areas)) { ?>
	<p><i>There are no areas yet.</i></p>
<?php } else { ?>

	<table class="list-table">
		<tbody>
			<?php foreach ($areas as $area) { ?>
				<tr>
					<td>
						<a href="<?php echo $url('areas', 'edit', $area->id); ?>"><?php echo $h($area->label); ?></a>
					</td>
					<td>
						<a href="<?php echo $url('areas', 'delete', $area->id); ?>"><i class="fa fa-trash-o"></i> Delete</a>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>

<?php } ?>

<?php echo $button('New Area&hellip;', 'plus-circle', 'areas', 'add'); ?>

<br><br>
<hr>

<h2 style="margin-bottom: 0;">Blocks</h2>
<p style="font-style: italic; margin-top: 5px; line-height: 1.2em;">Blocks are like mini-templates which contain a pre-defined set of fields. You (the site developer) specify which fields you want in each block and exactly how the fields are outputted in markup, then the site editors will be able to add blocks to areas on their pages.</p>

<?php if (empty($blocks)) { ?>
	<p><i>There are no blocks yet.</i></p>
<?php } else { ?>

	<table class="list-table">
		<tbody>
			<?php foreach ($blocks as $block) { ?>
				<tr>
					<td>
						<a href="<?php echo $url('blocks', 'edit', $block->id); ?>"><?php echo $h($block->label); ?></a>
					</td>
					<td>
						<a href="<?php echo $url('fields', 'index', $block->id); ?>"><i class="fa fa-list"></i> Fields</a>
					</td>
					<td>
						<a href="<?php echo $url('blocks', 'delete', $block->id); ?>"><i class="fa fa-trash-o"></i> Delete</a>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>

<?php } ?>

<?php echo $button('New Block&hellip;', 'plus-circle', 'blocks', 'add'); ?>
