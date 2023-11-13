export default function Loading( props: React.HTMLProps<HTMLButtonElement> ) {
	return (
		<button
			{ ...props }
			className="flex justify-center space-x-0.5 items-center mr-4 mt-2 border-none border-l border-pink-300 bg-transparent"
			type="button"
		>
			<span className="w-2 h-2 bg-[#E879F9] rounded-full animate-loader"></span>
			<span className="w-2 h-2 bg-[#E879F9] rounded-full delay-1000 animate-loader animation-delay-200"></span>
			<span className="w-2 h-2 bg-[#E879F9] rounded-full animate-loader animation-delay-400"></span>
		</button>
	);
}
