import { registerBlockType } from '@wordpress/blocks';
import { useState, useRef } from '@wordpress/element';
import { InspectorControls, useBlockProps, RichText } from '@wordpress/block-editor';
import {
	PanelBody,
	TextareaControl,
	ColorPalette,
	__experimentalHStack as HStack,
	FlexItem,
	__experimentalZStack as ZStack,
	ColorIndicator,
	Flex,
	BaseControl,
	Dropdown,
} from '@wordpress/components';
import {
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	Button,
	FontSizePicker,
} from '@wordpress/components';
import { Chart } from 'react-chartjs-2';
import _ChartJS from 'chart.js/auto';
import apiFetch from '@wordpress/api-fetch';
import { colord } from 'colord';

const LabeledColorIndicators = ({ indicators, label }) => (
	<HStack justify="flex-start">
		<ZStack isLayered={false} offset={-8}>
			{indicators.map((indicator, index) => (
				<Flex key={index} expanded={false}>
					<ColorIndicator colorValue={indicator} />
				</Flex>
			))}
		</ZStack>
		<FlexItem className="block-editor-panel-color-gradient-settings__color-name" title={label}>
			{label}
		</FlexItem>
	</HStack>
);

const DEFAULT_COLORS = [
	{
		backgroundColor: colord('#c60800').alpha(0.5).toRgbString(),
		borderColor: '#c60800',
		color: '#c60800',
	},
	{
		backgroundColor: colord('#f7cb00').alpha(0.5).toRgbString(),
		borderColor: '#f7cb00',
		color: '#f7cb00',
	},
	{
		backgroundColor: colord('#fe6005').alpha(0.5).toRgbString(),
		borderColor: '#fe6005',
		color: '#fe6005',
	},
];

const fontSizes = [
	{
		name: 'Small',
		size: 12,
	},
	{
		name: 'Medium',
		size: 14,
	},
	{
		name: 'Larger',
		size: 16,
	},
];

registerBlockType('ai/chart', {
	title: 'AI Chart',
	icon: 'chart-bar',
	category: 'common',
	attributes: {
		data: {
			type: 'string',
			default: '',
		},
		title: {
			type: 'string',
			default: '',
		},
		type: {
			type: 'string',
			default: 'pie',
		},
		credits: {
			type: 'string',
		},
		fontSize: {
			type: 'number',
			default: 12,
		},
	},
	edit: (props) => {
		const { attributes, setAttributes } = props;
		const data = attributes.data ? JSON.parse(attributes.data) : null;
		const [isLoading, setIsLoading] = useState(false);
		const [error, setError] = useState('');
		const [messages, setMessages] = useState([]);

		const updateLatestAssistantMessage = (updatedAttributes) => {
			const newAttributes = {
				...attributes,
				...updatedAttributes,
			};

			const message = {
				role: 'assistant',
				function_call: {
					name: 'create_chart',
					arguments: JSON.stringify([{
						type: newAttributes.type,
						data: JSON.parse(newAttributes.data),
						title: newAttributes.title,
						credits: newAttributes.credits,
					}]),
				},
				content: '',
			};

			const newMessages = [...messages.slice(0, -1), message];
			setMessages(newMessages);
		};
		const onChangeData = (newData) => {
			try {
				let data = JSON.parse(newData);
				updateLatestAssistantMessage({data: JSON.stringify(data)});
				setAttributes({ data: JSON.stringify(data) });
			} catch (e) {
				console.error(e);
				return false;
			}
		};

		const onSubmitNewChart = async (e) => {
			e.preventDefault();
			const formData = new FormData(e.target);
			const data = formData.get('data');
			const prompt = formData.get('prompt');

			const message = `
				\`\`\`
				${data}
				\`\`\`
				${prompt}
			`;
			const newMessages = [
				...messages,
				{
					role: 'user',
					content: message,
				},
			];
			setMessages(newMessages);

			let response = await sendMessages(newMessages);

			response.data.datasets = response.data.datasets?.map((dataset, index) => {
				if (!dataset.color) {
					return {
						...dataset,
						...DEFAULT_COLORS[index],
					};
				}
			});

			setAttributes({
				...response,
				data: JSON.stringify(response.data),
			});
		};

		const onSubmitChart = async (e) => {
			e.preventDefault();
			const formData = new FormData(e.target);
			const prompt = formData.get('prompt');

			const newMessages = [
				...messages,
				{
					role: 'user',
					content: prompt,
				},
			];
			setMessages(newMessages);

			let response = await sendMessages(newMessages);

			response.data.datasets = response.data.datasets?.map((dataset, index) => {
				if (!dataset.color) {
					return {
						...dataset,
						...DEFAULT_COLORS[index],
					};
				}
				return dataset;
			});

			setAttributes({
				...response,
				data: JSON.stringify(response.data),
			});

			e.target.querySelector('input[name="prompt"]').value = '';
		};

		const sendMessages = async (messages) => {
			setIsLoading(true);
			setError('');

			try {
				let response = await apiFetch({
					path: '/ai/v1/chart',
					method: 'POST',
					data: {
						messages,
					},
				});
				setMessages([
					...messages,
					{
						role: 'assistant',
						function_call: {
							name: 'create_chart',
							arguments: JSON.stringify([response]),
						},
						content: '',
					},
				]);
				return response;
			} catch (e) {
				setError(e.message);
			} finally {
				setIsLoading(false);
			}
		};

		const textarea = useRef(null);
		const onPaste = (e) => {
			// For the future if we want to massage this data.
			let data = e.clipboardData.getData('text/plain');
			e.preventDefault();
			textarea.current.value = data;
		};
		const blockProps = useBlockProps();
		console.log(data)
		if (!data) {
			return (
				<div {...blockProps}>
					<div className="tailwind">
						<form onSubmit={onSubmitNewChart}>
							<label className="text-gray-500 text-sm">
								Paste any raw data below to generate a chart.
							</label>
							<textarea
								className="w-full h-80 text-sm p-2"
								ref={textarea}
								onPaste={onPaste}
								name="data"
							></textarea>
							<div className="bg-gradient-to-r from-gradient-blue to-gradient-pink rounded p-[2px] my-2">
								<input
									type="text"
									name="prompt"
									placeholder="Optionally describe what you'd like to see in the chart."
									className={`block border-none py-2 px-4 bg-white w-full text-sm flex-1 focus:shadow-none placeholder:text-[#4667DE] text-[#4667DE] ${
										isLoading && 'animate-pulse'
									}`}
								/>
							</div>

							<Button disabled={isLoading} variant="primary" type="submit">
								{isLoading ? 'Generating Chart...' : 'Generate Chart'}
							</Button>
						</form>
					</div>
				</div>
			);
		}

		return (
			<div {...blockProps}>
				<InspectorControls>
					<PanelBody title="Options">
						<Dropdown
							renderToggle={({ isOpen, onToggle }) => (
								<Button onClick={onToggle} variant="secondary">
									Edit Data
								</Button>
							)}
							renderContent={() => (
								<TextareaControl
									style={{ width: '600px', height: '400px' }}
									label="Data"
									value={JSON.stringify(data, null, 4)}
									onChange={onChangeData}
								/>
							)}
							style={{ marginBottom: '20px' }}
						/>
						<ToggleGroupControl
							label="Chart Type"
							value={attributes.type}
							isBlock
							onChange={(type) => {
								updateLatestAssistantMessage({type});
								setAttributes({ type })
							}}
						>
							<ToggleGroupControlOption value="doughnut" label="Doughnut" />
							<ToggleGroupControlOption value="line" label="Line" />
							<ToggleGroupControlOption value="bar" label="Bar" />
						</ToggleGroupControl>

						<BaseControl label="Colors">
							{data.datasets?.map((dataset, index) => (
								<Dropdown
									key={index}
									className="block-editor-tools-panel-color-gradient-settings__dropdown"
									renderToggle={({ isOpen, onToggle }) => (
										<Button onClick={onToggle}>
											<LabeledColorIndicators
												indicators={[dataset.color]}
												label={dataset.label || `Database ${index}`}
											></LabeledColorIndicators>
										</Button>
									)}
									renderContent={() => (
										<ColorPalette
											value={dataset.color}
											colors={DEFAULT_COLORS.map((color) => ({
												name: 'Custom',
												color: color.color,
											}))}
											clearable={false}
											onChange={(color) => {
												color = colord(color).toRgb();
												const att = {
													data: JSON.stringify({
														...data,
														datasets: data.datasets.map((dataset, i) => {
															if (i === index) {
																dataset.backgroundColor = `rgba(${color.r}, ${
																	color.g
																}, ${color.b}, ${color.a / 2})`;
																dataset.borderColor = `rgba(${color.r}, ${color.g}, ${color.b}, ${color.a})`;
																dataset.color = `rgba(${color.r}, ${color.g}, ${color.b}, ${color.a})`;
															}
															return dataset;
														}),
													}),
												};

												updateLatestAssistantMessage(att);
												setAttributes(att);
											}}
										/>
									)}
								/>
							))}
						</BaseControl>
						<FontSizePicker
							fontSizes={fontSizes}
							value={attributes.fontSize}
							onChange={(fontSize) => setAttributes({ fontSize })}
						/>
					</PanelBody>
				</InspectorControls>
				<div className="ai-chart">
					<RichText
						tagName="h5"
						value={attributes.title}
						onChange={(title) => props.setAttributes({ title })}
						placeholder="Enter title..."
					/>
					{data && (
						<div className="tailwind">
							<div className="">
								<Chart
									type={attributes.type}
									options={{
										font: { size: 22 },
										plugins: {
											legend: {
												labels: {
													font: {
														size: attributes.fontSize,
													},
												},
											},
										},
										scales: {
											y: {
												ticks: {
													font: {
														size: attributes.fontSize,
													}
												},
											},
											x: {
												ticks: {
													font: {
														size: attributes.fontSize,
													}
												},
											},
										},
									}}
									data={data}
								/>
								<RichText
									tagName="p"
									style={{
										textAlign: 'center',
										fontStyle: 'italic',
										color: '#999',
										fontSize: '0.8rem',
									}}
									value={attributes.credits}
									onChange={(credits) => props.setAttributes({ credits })}
									placeholder="Enter credits..."
								/>
							</div>
							{props.isSelected && messages.length > 0 && ( // Only show prompt if we have message history.
								<form
									className={`bg-gradient-to-r from-gradient-blue to-gradient-pink rounded p-[2px] my-2 ${
										isLoading && 'animate-pulse'
									}`}
									onSubmit={onSubmitChart}
								>
									<input
										type="text"
										name="prompt"
										placeholder="What would you like to do with the chart?"
										className={`block border-none py-2 px-4 bg-white w-full text-sm flex-1 focus:shadow-none placeholder:text-[#4667DE] text-[#4667DE]`}
									/>
								</form>
							)}
						</div>
					)}
				</div>
			</div>
		);
	},
	save: (props) => {
		return null;
	},
});
