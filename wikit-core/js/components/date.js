import classnames from 'classnames';
import { useState } from '@wordpress/element';
import { BaseControl, Button, DateTimePicker, DatePicker, Popover, Flex, FlexItem, FlexBlock, Icon } from '@wordpress/components';
import { getSettings, format as formatDate } from '@wordpress/date';

const dateSettings = getSettings();

export function DateTimeControl( {
	allDay = false,
	allDayFormat = dateSettings.formats.date,
	className = '',
	date,
	format = dateSettings.formats.datetime,
	is12Hour = true,
	label,
	noDate = 'Select a date',
	onChange,
	position = 'middle center',
	noArrow = false,
	...props
} ) {
	const [ isOpen, setIsOpen ] = useState( false );

	const Picker = allDay ? DatePicker : DateTimePicker;
	const dateFormat = allDay ? allDayFormat : format;

	return (
		<BaseControl label={ label }>
			<Flex>
				<FlexBlock>
					<Button
						variant="link"
						onClick={ () => ! isOpen && setIsOpen( true ) }
						disabled={ isOpen }
					>
						{ date ? formatDate( dateFormat, date ) : noDate }
					</Button>
				</FlexBlock>
				{ date ? (
					<FlexItem>
						<Button
							variant="link"
							isDestructive
							onClick={ () => onChange( null ) }
							style={ { textDecoration: 'none' } }
						>
							<Icon
								icon="remove"
								size="12"
							/>
						</Button>
					</FlexItem>
				) : null }
			</Flex>
			{ isOpen ? (
				<Popover
					onClose={ () => {
						setIsOpen( false );

						if ( typeof props.onClose === 'function' ) {
							props.onClose();
						}
					} }
					position={ position }
					className={ classnames( 'wdg-date-control', className ) }
					noArrow={ noArrow }
					{ ...props }
				>
					<Picker
						currentDate={ date }
						onChange={ onChange }
						is12Hour={ is12Hour }
					/>
				</Popover>
			) : null }
		</BaseControl>
	);
}
