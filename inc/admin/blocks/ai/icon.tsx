import useId from './useId';

interface Props {
	className?: string,
}
export default function Icon( props: Props ) {
	const gradientId = useId();

	return (
		<svg
			className={ props.className }
			fill="none"
			height="19"
			style={ {
				fill: "transparent",
			} }
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 32 23"
			width="24"
		>
			<path
				d="M17.6522 10.604L21.094 7.16228M13.8395 6.79137L17.2813 3.34957L30.7483 16.8166L27.3065 20.2584L13.8395 6.79137ZM11.5913 11.8038C11.5913 14.5293 9.38193 16.7387 6.65649 16.7387C9.38193 16.7387 11.5913 18.9481 11.5913 21.6735C11.5913 18.9481 13.8007 16.7387 16.5262 16.7387C13.8007 16.7387 11.5913 14.5293 11.5913 11.8038ZM5.24408 1.88672C5.24408 4.09166 3.45662 5.87912 1.25168 5.87912C3.45662 5.87912 5.24408 7.66659 5.24408 9.87153C5.24408 7.66659 7.03155 5.87912 9.23649 5.87912C7.03155 5.87912 5.24408 4.09166 5.24408 1.88672Z"
				stroke="white"
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="2"
			/>
			<path
				d="M17.6522 10.604L21.094 7.16228M13.8395 6.79137L17.2813 3.34957L30.7483 16.8166L27.3065 20.2584L13.8395 6.79137ZM11.5913 11.8038C11.5913 14.5293 9.38193 16.7387 6.65649 16.7387C9.38193 16.7387 11.5913 18.9481 11.5913 21.6735C11.5913 18.9481 13.8007 16.7387 16.5262 16.7387C13.8007 16.7387 11.5913 14.5293 11.5913 11.8038ZM5.24408 1.88672C5.24408 4.09166 3.45662 5.87912 1.25168 5.87912C3.45662 5.87912 5.24408 7.66659 5.24408 9.87153C5.24408 7.66659 7.03155 5.87912 9.23649 5.87912C7.03155 5.87912 5.24408 4.09166 5.24408 1.88672Z"
				stroke={ `url(#${ gradientId })` }
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="2"
			/>
			<defs>
				<linearGradient
					gradientUnits="userSpaceOnUse"
					id={ gradientId }
					x1="1.25168"
					x2="33.7546"
					y1="21.6735"
					y2="11.3713"
				>
					<stop stopColor="#4667DE" />
					<stop offset="1" stopColor="#E879F9" />
				</linearGradient>
			</defs>
		</svg>
	);
}
