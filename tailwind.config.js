import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.vue",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },

            colors: {
                background: "var(--bg)",
                surface: "var(--bg-surface)",
                surface2: "var(--bg-surface2)",
                input: "var(--bg-input)",

                content: "var(--text)",
                "content-muted": "var(--text-2)",
                "content-faint": "var(--text-3)",

                border: "var(--border)",
                "border-strong": "var(--border-strong)",

                accent: {
                    DEFAULT: "var(--accent)",
                    light: "var(--accent-2)",
                },

                chart: {
                    1: "var(--chart-1)",
                    2: "var(--chart-2)",
                    3: "var(--chart-3)",
                    4: "var(--chart-4)",
                },
            },

            boxShadow: {
                sm: "var(--shadow-sm)",
                md: "var(--shadow-md)",
                lg: "var(--shadow-lg)",
                glass: "var(--shadow-glass)",
                raised: "var(--shadow-raised)",
                pressed: "var(--shadow-pressed)",
            },

            transitionTimingFunction: {
                "apple-fast": "cubic-bezier(0.4, 0, 0.2, 1)",
                "apple-base": "cubic-bezier(0.34, 1.56, 0.64, 1)",
                "apple-slow": "cubic-bezier(0.22, 1, 0.36, 1)",
            },
        },
    },

    safelist: [
        "scenario-icon-purple",
        "scenario-icon-pink",
        "scenario-icon-indigo",
    ],

    plugins: [forms],
};
