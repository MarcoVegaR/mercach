import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="from-primary flex aspect-square size-8 items-center justify-center rounded-md bg-gradient-to-br to-violet-500 text-white dark:text-black">
                <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">Caracoders Pro Services</span>
            </div>
        </>
    );
}
