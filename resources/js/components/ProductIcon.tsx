import React from 'react';

import { ItemCategory } from '../types';

const beansIcon = '/assets/beans.svg';
const milkIcon = '/assets/milk.svg';
const cupsIcon = '/assets/cups.svg';
const syrupIcon = '/assets/syrup.svg';
const pastryIcon = '/assets/pastry.svg';
const teaIcon = '/assets/tea.svg';
const sugarIcon = '/assets/sugar.svg';
const cleaningIcon = '/assets/cleaning.svg';
const seasonalIcon = '/assets/seasonal.svg';
const foodIcon = '/assets/food.svg';
const sauceIcon = '/assets/sauce.svg';

interface ProductIconProps {
    category: ItemCategory;
    className?: string;
}

const ProductIcon: React.FC<ProductIconProps> = ({ category, className }) => {
    let src = beansIcon;
    let alt = 'Product';

    switch (category) {
        case ItemCategory.BEANS:
            src = beansIcon;
            alt = 'Beans';
            break;
        case ItemCategory.MILK:
            src = milkIcon;
            alt = 'Milk';
            break;
        case ItemCategory.CUPS:
            src = cupsIcon;
            alt = 'Cups';
            break;
        case ItemCategory.SYRUP:
            src = syrupIcon;
            alt = 'Syrup';
            break;
        case ItemCategory.PASTRY:
            src = pastryIcon;
            alt = 'Pastry';
            break;
        case ItemCategory.TEA:
            src = teaIcon;
            alt = 'Tea';
            break;
        case ItemCategory.SUGAR:
            src = sugarIcon;
            alt = 'Sugar';
            break;
        case ItemCategory.CLEANING:
            src = cleaningIcon;
            alt = 'Cleaning Supplies';
            break;
        case ItemCategory.SEASONAL:
            src = seasonalIcon;
            alt = 'Seasonal Item';
            break;
        case ItemCategory.FOOD:
            src = foodIcon;
            alt = 'Hot Food';
            break;
        case ItemCategory.SAUCE:
            src = sauceIcon;
            alt = 'Sauce';
            break;
        default:
            src = beansIcon;
    }

    return <img src={src} className={className} alt={alt} />;
};

export default ProductIcon;
