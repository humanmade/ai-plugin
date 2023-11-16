import { Fill } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { Fragment } from '@wordpress/element';

import Icon from './icon';
import useAiSummary from './useAiSummary';

const MINI_BUTTON_CLASS = [
	'text-[13px] leading-6',
	'min-h-[32px]',
	'flex items-center',
	'px-[0.5em] py-0 mb-[5px]',
	'text-[#303030] bg-[#F7F7F7]',
	'shadow-[rgba(0,0,0,0.1)_0px_-2px_0px_0px_inset]',
	'border border-solid border-[#DBDBDB] rounded',
	'cursor-pointer',

	'hover:text-black hover:bg-white hover:border-[var(--yoast-color-border--default)]',
].join( ' ' );

const MEGA_BUTTON_CLASS = [
	'text-[14px] leading-[1.2]',
	'min-h-[32px]',
	'flex items-center',
	'p-3 pt-[10px]',
	'text-[#303030] bg-[#F7F7F7]',
	'shadow-[rgba(0,0,0,0.1)_0px_-2px_0px_0px_inset]',
	'border border-solid border-[#DBDBDB] rounded',
	'cursor-pointer',

	'hover:text-[var(--yoast-color-dark)] hover:bg-[var(--yoast-color-secondary-darker)] hover:border-[var(--yoast-color-border--default)]',
].join( ' ' );

function DescriptionSuggest() {
	const { loading, submitPrompt } = useAiSummary();
	const { updateData } = useDispatch( 'yoast-seo/editor' );

	// @ts-ignore
	const yoastComponents = window.yoast.components || window.yoast.componentsNew;
	const Loader = yoastComponents.Loader;

	const onSuggest = async () => {
		const res = await submitPrompt( 'Write an SEO meta description for this content. Use a maximum of 150 characters.' );
		updateData( {
			description: res,
		} );
	};

	return (
		<div
			className="tailwind altis-ai-yoast-description"
		>
			<button
				className={ MINI_BUTTON_CLASS }
				disabled={ loading }
				type="button"
				onClick={ onSuggest }
			>
				{ loading ? (
					<Loader
						className="h-4 w-auto mr-2 align-middle"
					/>
				) : (
					<Icon
						className="h-4 w-auto mr-2 align-middle"
					/>
				) }
				Write it for me
			</button>
		</div>
	);
}

// https://github.com/Yoast/wordpress-seo/blob/f23a53a0fcb4cc2bb4e9a3438ede191d1bd9a013/packages/social-metadata-forms/src/SocialMetadataPreviewForm.js#L250
const SOCIAL_SERVICES = [
	'Facebook',
	'Twitter',
];
interface SocialSuggestProps {
	service: string,
}

function useSocialUpdate( service: string ) {
	const actions = useDispatch( 'yoast-seo/editor' );
	switch ( service ) {
		case 'Facebook':
			return actions.setFacebookPreviewDescription;

		case 'Twitter':
			return actions.setTwitterPreviewDescription;

		default:
			return () => {};
	}
}

function SocialSuggest( props: SocialSuggestProps ) {
	const { loading, submitPrompt } = useAiSummary();
	const updateDescription = useSocialUpdate( props.service );

	// @ts-ignore
	const yoastComponents = window.yoast.components || window.yoast.componentsNew;
	const Loader = yoastComponents.Loader;

	const onSuggest = async () => {
		const res = await submitPrompt(
			`Write a social media description for this content suitable for sharing on ${ props.service }. Use a maximum of 150 characters. Do not use quotes.`
		);

		// Trim off any errant quotes.
		const trimmed = res.replace( /^"|"$/g, '' );
		updateDescription( trimmed );
	};

	return (
		<div
			className="tailwind altis-ai-yoast-description"
		>
			<button
				className={ MINI_BUTTON_CLASS }
				disabled={ loading }
				type="button"
				onClick={ onSuggest }
			>
				{ loading ? (
					<Loader
						className="h-4 w-auto mr-2 align-middle"
					/>
				) : (
					<Icon
						className="h-4 w-auto mr-2 align-middle"
					/>
				) }
				Write it for me
			</button>
		</div>
	);
}

interface KeywordSuggestProps {
	location: 'metabox' | 'sidebar',
}

function KeywordSuggest( props: KeywordSuggestProps ) {
	const { loading, submitPrompt } = useAiSummary();
	const { setFocusKeyword } = useDispatch( 'yoast-seo/editor' );

	// @ts-ignore
	const yoastComponents = window.yoast.components || window.yoast.componentsNew;
	const Loader = yoastComponents.Loader;

	const onSuggest = async () => {
		const res = await submitPrompt( 'Write a focus keyword for this content. Consider the most likely search terms a user would enter to find this page. Use a maximum of 50 characters. Provide the answer as a phrase without a full stop, and avoid using function words like "the" or "and".' );

		// Strip any trailing punctuation, and lowercase it.
		const keyword = res.replace( /\.$/, '' ).toLowerCase();
		setFocusKeyword( keyword );
	};

	const classes = [
		'yoast',
		'px-4',
		props.location === 'metabox' ? 'float-right mt-[-58px]' : '-mt-2 mb-4',
	].join( ' ' );

	return (
		<div
			className="tailwind altis-ai-yoast-keyword"
		>
			<div className={ classes }>
				<button
					className="yoast-button yoast-button--secondary"
					disabled={ loading }
					type="button"
					onClick={ onSuggest }
				>
					{ loading ? (
						<Loader
							className="h-4 w-auto mr-2 align-middle"
						/>
					) : (
						<Icon
							className="h-4 w-auto mr-2 align-middle"
						/>
					) }
					Write it for me
				</button>
			</div>
		</div>
	);
}

export default function YoastPlugin() {
	const yoastDispatch = useDispatch( 'yoast-seo/editor' );
	// @ts-ignore
	if ( ! yoastDispatch || ! window.yoast || ( ! window.yoast.compontents && ! window.yoast.componentsNew ) ) {
		return null;
	}

	return (
		<>
			{ /* Google/SEO meta */ }
			<Fill
				name="PluginComponent-yoast-google-preview-description-metabox"
			>
				<DescriptionSuggest />
			</Fill>
			<Fill
				name="PluginComponent-yoast-google-preview-description-modal"
			>
				<DescriptionSuggest />
			</Fill>

			{ /* Social media services (Facebook and Twitter hardcoded in Yoast) */ }
			{ SOCIAL_SERVICES.map( service => (
				<Fragment key={ service }>
					<Fill
						name={ `PluginComponent-${ service.toLowerCase() }-description-input-metabox` }
					>
						<SocialSuggest
							service={ service }
						/>
					</Fill>
					<Fill
						name={ `PluginComponent-${ service.toLowerCase() }-description-input-modal` }
					>
						<SocialSuggest
							service={ service }
						/>
					</Fill>
				</Fragment>
			) ) }

			{ /* Focus keyword */ }
			<Fill
				name="YoastAfterKeywordInputMetabox"
			>
				<KeywordSuggest
					location="metabox"
				/>
			</Fill>
			<Fill
				name="YoastAfterKeywordInputSidebar"
			>
				<KeywordSuggest
					location="sidebar"
				/>
			</Fill>
		</>
	);
}
