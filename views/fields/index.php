<div class="sortable-container" data-sortable-save-url="<?php echo $url('fields', 'sort', $block->id); ?>" data-sortable-save-token-name="<?php echo $session->CSRF->getTokenName(); ?>" data-sortable-save-token-value="<?php echo $session->CSRF->getTokenValue(); ?>">
	<table class="list-table">
		<thead>
			<tr>
				<th>Type</th>
				<th>Handle</th>
				<th>Label</th>
				<th>&nbsp;</th>
				<th>&nbsp;</th>
				<th>&nbsp;</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($fields as $handle => $field) { ?>
				<?php if ($field->name == 'title') { continue; } ?>
				<tr data-sortable-id="<?php echo $field->id; ?>">
					<td>
						<?php echo $h($field->type->shortName); ?>
					</td>
					<td>
						<?php echo $handle; ?>
					</td>
					<td>
						<?php echo $h($field->label); ?>
					</td>
					<td>
						<div class="sortable-handle" title="Drag To Sort"><i class="fa fa-arrows-v"></i></div>
					</td>
					<td>
						<a href="<?php echo $url('fields', 'edit', $field->id); ?>"><i class="fa fa-pencil"></i> Edit</a>
					</td>
					<td>
						<a href="<?php echo $url('fields', 'delete', $field->id); ?>"><i class="fa fa-trash-o"></i> Delete</a>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</div><!-- .sortable-container -->

<p><?php echo $button('Add New Field&hellip;', 'plus-circle', 'fields', 'add', $block->id); ?></p>
<hr>
<p><?php echo $button('Back', 'reply'); ?></p>
