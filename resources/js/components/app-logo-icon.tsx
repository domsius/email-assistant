import { SVGAttributes } from "react";

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
  return (
    <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2zm16 2l-8 5-8-5h16zm0 12V8l-8 5-8-5v10h16z"
      />
    </svg>
  );
}
