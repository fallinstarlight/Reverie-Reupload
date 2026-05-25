use `Reverie`;

-- Procedures and triggers, threat them with caution :) --
-- -------------------------------------------------------------------------------------------------- --
/* Validation and handling of the insertion of a new employee */
drop procedure if exists `inEmployee`;
delimiter $$
create procedure `inEmployee`
/* Data to receive from the API */
(in `in_name` varchar(50), in `in_surname` varchar(50), in `in_username` varchar(20), in `in_passwordHash` varchar(255), 
in `in_shift` varchar(30), in `in_phone` varchar(15), in `in_photo` varchar(255))
begin
	/* checks if the employee username already exists */
	if not exists(select employee_id from employees where `in_username` = e_username)
    then
		/* Checks if the name and surname combination already exist */
		if not exists(select employee_id from employees where `in_name` = e_name and `in_surname` = e_surname)
        then
			if in_photo = ' ' or in_photo is null then
				insert into employees(`e_name`, `e_surname`, `e_username`, `e_passwordHash`, `e_shift`, `e_phone`) 
                values(in_name, in_surname, in_username, in_passwordHash, in_shift, in_phone);
			else
				insert into employees(`e_name`, `e_surname`, `e_username`, `e_passwordHash`, `e_shift`, `e_phone`, `e_profilePhoto`) 
                values(in_name, in_surname, in_username, in_passwordHash, in_shift, in_phone, in_photo);
            end if;
		else
			signal sqlstate '45000' set message_text = 'Employee already exists';
        end if;
	else
		signal sqlstate '45000' set message_text = 'Employee already exists';
    end if;
end $$
-- -------------------------------------------------------------------------------------------------- --

-- -------------------------------------------------------------------------------------------------- --
/* Handle the insertion of new products */
drop procedure if exists `inProduct`$$
create procedure `inProduct`
/* Values to receive from the API */
(in `in_code` varchar(10), in `in_name` varchar(50), in `in_description` varchar(255), in `in_price` decimal(10,2), in `in_amount` int unsigned, in `in_category` int)
begin
	/* Based on the specified amount of stock of that product we set it's state*/
	declare state varchar(10);
    if(`in_amount` > 0) then
		set state = 'available';
	else
		set state = 'soldout';
	end if;
    /* Check if a product with that code already exists */
	if not exists(select product_code from products where `in_code` = product_code)
    then
    /* Checks if a product with that exact name already exists */
		if not exists(select product_code from products where `in_name` = p_name)
        then
			if exists(select category_id from categories where `in_category` = category_id)
            then
				insert into products(`product_code`, `p_name`, `p_description`, `p_price`, `p_state`, `p_amount`, `category_id`) values(in_code, in_name, in_description, in_price, state, in_amount, in_category);
			else
				signal sqlstate '45000' set message_text = 'That is not a valid category id';
			end if;
        end if;
	else
		signal sqlstate '45000' set message_text = 'Product already exists';
    end if;
end $$
-- -------------------------------------------------------------------------------------------------- --

-- -------------------------------------------------------------------------------------------------- --
/* Procedure to decrement the amount of stock depending on how many products were sold */
drop procedure if exists `decProduct` $$
create procedure `decProduct`
(in `in_code` varchar(10), in `in_amount` int)
begin
	declare amount int;
    set amount = (select p_amount from products where product_code = in_code);
	/* Check if the specified product exists */
	if exists(select product_code from products where product_code = in_code and p_state != 'discontinued')
    then
    /* If the amount in which the stock should be decreased in is higher than the actual stock, we block the petition */
		if amount > in_amount
        then
			/* Update the stock otherwise*/
			update products set p_amount = p_amount - in_amount where product_code = in_code;
            /* If the amount reaches 0, we change the state to soldout */
        elseif amount = in_amount
        then
			update products set p_amount = 0 where product_code = in_code;
			update products set p_state = 'soldout' where product_code = in_code;
		else
			signal sqlstate '45000' set message_text = 'Not enough product';
        end if;
	else
		signal sqlstate '45000' set message_text = 'Product does not exist or is discontinued';
    end if;
end $$
-- -------------------------------------------------------------------------------------------------- --

-- -------------------------------------------------------------------------------------------------- --
/* Porcedure to increase the amount of a product in 1, mimicing a manual process of inserting a product at a time */
drop procedure if exists `incProduct` $$
create procedure `incProduct`
(in `in_code` varchar(10))
begin
	/* Checking that the product exists */
	if exists(select product_code from products where product_code = in_code and p_state != 'discontinued')
    then
    /* Decreasing just by one */
		update products set p_amount = p_amount + 1 where product_code = in_code;
        /* If the product was soldout, we change it to available again */
        if(select p_state from products where product_code = in_code) = 'soldout'
        then
			update products set p_state = 'available' where product_code = in_code;
        end if;
	else
		signal sqlstate '45000' set message_text = 'Product does not exist or is discontinued';
	end if;
end $$
-- -------------------------------------------------------------------------------------------------- --

-- -------------------------------------------------------------------------------------------------- --
/* Procedure to insert a new sale into the sales table */
drop procedure if exists `insert_Sale` $$
create procedure `insert_Sale`
/* Values to take, since the sale amount (s_amount) is yet not known, we ignore it */
(in `in_date` date, in `in_saler` int)
begin
	/* Verifying that the sale is not "happening" on a future day */
	if in_date > curdate()
    then
		signal sqlstate '45000' set message_text = 'Invalid date';
	else 
    /* Checking that the provided employee exists */ 
		if not exists(select employee_id from employees where employee_id = in_saler and state = 'alive')
        then
			signal sqlstate '45000' set message_text = "Employee doesn't exist";
		else 
			insert into sales(`s_date`, `s_saler`) values (in_date, in_saler);
        end if;
	end if;
end $$
-- -------------------------------------------------------------------------------------------------- --

-- -------------------------------------------------------------------------------------------------- --
/* Procedure to update employees */
drop procedure if exists `updateEmployee`$$
create procedure `updateEmployee`
(in `in_id` int, in `new_name` varchar(50), in `new_surname` varchar(50), in `new_username` varchar(20), in `new_passwordHash` varchar(255), 
in `new_shift` varchar(30), in `new_phone` varchar(15), in `new_photo` varchar(255), in `new_role` varchar(15))
begin
	/* Validate that the employee exists */
	if not exists(select employee_id from employees where employee_id = `in_id`)
    then
		signal sqlstate '45000' set message_text = 'Employee does not exist';
	else
		update employees
        set
        /* Use the coalesce function so if the passed value is null we ignore it and pass just the new values */
			`e_name` = coalesce(nullif(new_name, ''), e_name),
            `e_surname` = coalesce(nullif(new_surname, ''), e_surname),
            `e_username` = coalesce(nullif(new_username, ''), e_username),
            `e_passwordHash` = coalesce(nullif(new_passwordHash, ''), e_passwordHash),
            `e_shift` = coalesce(nullif(new_shift, ''), e_shift),
            `e_phone` = coalesce(nullif(new_phone, ''), e_phone),
            `e_profilePhoto` = coalesce(nullif(new_photo, ''), e_profilePhoto),
            `e_role` = coalesce(new_role, e_role)
		where employee_id = `in_id`;
        end if;
end $$
-- -------------------------------------------------------------------------------------------------- --

-- -------------------------------------------------------------------------------------------------- --
/* Procedure to update products */
drop procedure if exists `updateProduct`$$
create procedure `updateProduct`
(in `in_code` varchar(10), in `new_name` varchar(50), in `new_description` varchar(255), in `new_price` decimal(10,2), in `new_category` int, 
 in `new_photo` varchar(255), in `new_state` varchar(20))
begin
	/* Validate that the employee exists */
	if not exists(select product_code from products where product_code = `in_code`)
    then
		signal sqlstate '45000' set message_text = 'Product does not exist';
	else
		/* Validate that the category exists only if provided */
		if not exists(select category_id from categories where category_id = new_category) and new_category is not null
		then
			signal sqlstate '45000' set message_text = 'The provided category does not exist';
		else
			update products
			set
			/* Use the coalesce function so if the passed value is null we ignore it and pass just the new values */
				`p_name` = coalesce(new_name, p_name),
				`p_description` = coalesce(new_description, p_description),
				`p_price` = coalesce(new_price, p_price),
				`category_id` = coalesce(new_category, category_id),
				`p_photo` = coalesce(new_photo, p_photo),
				`p_state` = coalesce(new_state, p_state)
			where product_code = `in_code`;
		end if;
	end if;
end $$
-- -------------------------------------------------------------------------------------------------- --

-- -------------------------------------------------------------------------------------------------- --
/* Trigger to insert or update the daily sales report and the total sales from each employee */
drop trigger if exists `afterSale` $$
/* Executes everytime the sales table is updated, which happens everytime we create a new record in Sale_sold_product 
which implies that the trigger will re run everytime the amount of that sale changes */
create trigger `afterSale` after update on sales for each row
begin
	/* Variable for a daily report (if exists) */
	declare rp_id int unsigned;
    declare mostSoldProduct varchar(10);
    /* Checking that the employee exists and is working */
	if exists (select employee_id from employees where new.s_saler = employee_id and state = 'alive')
    then
		/* Checking the sale happened on a valid date (today or in the past) */
		if(new.s_date <= curdate())
        then
        /* Calculate the most sold product of the day */
			set mostsoldproduct = (
				select product_code 
				from (
					select ssp.product_code, sum(ssp.p_amountSold) as total_sold
					from sale_sold_products ssp 
					join sales s on ssp.sale_id = s.sale_id 
					where s.s_date = curdate()
					group by ssp.product_code
					order by total_sold desc
					limit 1
				) as top_product
			);
			/* Increase both the todaysales and totala sales of the employee, we substract the old stored amount to prevent
            the amount to be duplicated */
            update employees set e_todaySales = e_todaySales + new.s_amount - old.s_amount 
				where employee_id = new.s_saler and new.s_date = curdate();
            update employees set e_totalSales = e_totalSales + new.s_amount - old.s_amount 
				where employee_id = new.s_saler;
            /* We check if there's already a report for the current day, if not we create it */
            if not exists(select dailyReport_id from daily_report where dr_date = curdate())
            then
				insert into daily_report(dr_date, dr_moneyGained, dr_mostSoldProduct, dr_totalSales) 
                values(curdate(), new.s_amount, mostsoldproduct, 1);
            else
				set rp_id = (select dailyReport_id from daily_report where dr_date = new.s_date);
				update daily_report set dr_moneyGained = dr_moneyGained + new.s_amount - old.s_amount where dailyReport_id = rp_id;
                update daily_report set dr_mostSoldProduct = mostsoldproduct where dailyReport_id = rp_id;
                update daily_report set dr_totalSales = (select count(DISTINCT sale_id) from sales where s_date = curdate());
			end if;
		else
            signal sqlstate '45000' set message_text = 'invalid date';
        end if;
	else
        signal sqlstate '45000' set message_text = 'invalid employee';
    end if;
end $$
-- -------------------------------------------------------------------------------------------------- --

-- -------------------------------------------------------------------------------------------------- --
/* Calculate the amount of money gained during a sale based on the products sold and the amount of each one sold */
drop trigger if exists `totalSale` $$
/* Will run after every insertion of sale_sold_products */
create trigger `totalSale` after insert on sale_sold_products for each row
begin
	/* Variable to store the total amount */
	declare sale_total int;
    /* Checking the sale exists */
	if exists (select sale_id from sales where new.sale_id = sale_id)
    then
		/* Checking if the product sold exists */
		if exists(select product_code from products where new.product_code = product_code)
        then
			/* Calculate the total of the sale adding all the records where the sale id is found */
            call decProduct(new.product_code, new.p_amountSold);
			set sale_total = (select sum(p_price * p_amountSold) from sale_sold_products ssp join products p on p.product_code = ssp.product_code and ssp.sale_id = new.sale_id);
			/* Insert the amount into the corresponding sale row */
            update sales set s_amount = sale_total where sale_id = new.sale_id;
            update products set p_timesSold = p_timesSold + new.p_amountSold where product_code = new.product_code;
        else
			signal sqlstate '45000' set message_text = 'invalid product code';
        end if;
	else
        signal sqlstate '45000' set message_text = 'invalid sale id';
    end if;
end $$
-- -------------------------------------------------------------------------------------------------- --

-- -------------------------------------------------------------------------------------------------- --
/* Trigger to update the daily sales of an employee if he logins on a different day */
drop trigger if exists `updateLastLogin` $$
create trigger `updateLastLogin` 
before update on employees
for each row
begin
    if new.last_login != old.last_login then
        set new.e_todaySales = 0;
    end if;
end $$
-- -------------------------------------------------------------------------------------------------- --
-- -------------------------------------------------------------------------------------------------- --
-- -------------------------------------------------------------------------------------------------- --
delimiter ;




/* 
============================================================================================================
============================================================================================================
Code made by Francisco Emmanuel Luna Hidalgo Last checked 25/04/2026 
============================================================================================================
============================================================================================================
Instituto Tecnológico de Pachuca, Ingeniería en Sistemas Computacionales, Programación Web, proyecto final
============================================================================================================
============================================================================================================
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%%%%%%%%%##%%%%%%%%%%@@@@@@@@@@@@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%%#*++++++++++++++++++++++++++++*#%%%%%%@@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#*+++++++++++++++++++++++++++++++++++++++++++*##%%%@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%+++++++++++++++++++++++++++++++++++++++++++++++++++++*#%%@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%@@@@@#+++++++++++++++++++++++++++++++++++++++++++++++++++++++%@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@%%#+#%@@@@%*++++##+++++++++++++++++++++++++++++++++++++++++++++++%%@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@%%*+++++%%@@@@%*+++%@@@%#*+++++++++++++++++++++++++++++++++++++++++#%@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@%#++++++++*%@@@@@%*++%@@@@@@@%#+++++++++++++++++++++++++++++++++++++*%@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@%#++++++++++=#%@@@@@@#+%@@@@@@@@@@%#++++++++++++++++++++++++++++++++++%@@@@@@@@@@
    @@@@@@@@@@@@@@@@@%#++++++++++++++%@@@@@@@%%@@@@@@@@@@@@%%*++++++++++++++++++++++++++++++#%@@@@@@@@@@
    @@@@@@@@@@@@@@@%#++++++++++++++++*%@@@@@@@@@@@@@@@@@@@@@@@%#*++++++++++++++++++++++++++*%@@@@@@@@@@@
    @@@@@@@@@@@@@%%*++++++++++++++++++#%@@@@@@@@@@@@@@@@@@@@@@@@@%#+++++++++++++++++++++++*%@@@@@@@@@@@@
    @@@@@@@@@@@@%#+++++++++++++++++++++%%@@@@@@@@@@@@@@@@@@@@@@@@@@%%*++++++++++++++++++++#%@@@@@@@@@@@@
    @@@@@@@@@@@%*+++++++++++++++++++++++%@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%#+++++++++++++++++#%@@@@@@@@@@@@@
    @@@@@@@@@@%+++++++++++++++++++++++++*%@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#++++++++++++++*%@@@@@@@@@@@@@@
    @@@@@@@@%#+++++++++++++++++++++++++++#%@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#++++++++++++%@@@@@@@@@@@@@@@
    @@@@@@@%%+++++++++++++++++++++++++++++%@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#*++++++++#%@@@@@@@@@@@@@@@
    @@@@@@%%++++++++++++++++++++++++++++++*%@@@@@@@@@@@@@@%%%%%%%%%%%%%%%%@@@@%%##+--*%%@@@@@@@@@@@@@@@@
    @@@@@@%+++++++++++++++++++++++++++++++#%++*#%@@@@%%##*++++++++++++++++*#%%%%=...-=.=%@@@@@@@@@@@@@@@
    @@@@@%*+++++++++++++++++++++++++++++**:-+...-#%#*+++++++++++++++++++++++++##...:*...#@@@@@@@@@@@@@@@
    @@@@%*++++++++++++++++++++++++++++++#-..:+...=%+++++++++++++++++++++++++++*%:..*...:%@@@@@@@@@@@@@@@
    @@@%#+++++++++++++++++++++++++++++++#=...-=..+#++++++++++++++++++++++++++++#%++-..+%@@@@@@@@@@@@@@@@
    @@@%+++++++++++++++++++++++++++++**#%%+:..-**#+++++++++++++++++++++++++++++++*####**#%@@@@@@@@@@@@@@
    @@%#+++++++++++++++++++++++++*#%%@@@%#*#%#%#++++++++++++++++++++++++++++++++++++++++++#%@@@@@@@@@@@@
    @@%++++++++++++++++++++++*#%%@@@@@@%++++++++++++++++++++++++++++++++++++++=+===========*%@@@@@@@@@@@
    @%#+++++++++++++++++++*%%@@@@@@@@%+-=++++++++++++++++++++++++++++++++++++++=:...........:#@@@@@@@@@@
    @%*+++++++++++++++*#%@@@@@@@@@@@%+....-=++++++++++++++++++++=--==++++++++++++=-..........:*%@@@@@@@@
    @%++++++++++++++#%@@@@@@@@@@@@@%+........:=+++++++++++++++++++=.....:-==++++++++=..........#%@@@@@@@
    %#+++++++++++*%@@@@@@@@@@@@@@@%*.............:-===++++++++++++++-.................:-++=:....%@@@@@@@
    %#+++++++++#@@@@@@@@@@@@@@@@@@#:............:-::...::--===+++++++=-....................-*:..-%@@@@@@
    %#+++++=*%@@@@@@@@@@@@@@@@@@@%=..  ......:*=....................................+%@@%+...-:..+@@@@@@
    %#++++++++****#%@@@@@@@@@@@@@#:.     ....+.....:=*#*=:....  .... .....      ..+@@@#.:#@-.....-%@@@@@
    %*+++++++++*#%@@@@@@@@@@@@@@%+.. .   ...::...=@@@@=:-+%*:.                  .*@@@@@+..*@:....:#@@@@@
    %*=+++*##%%@@@@@@@@@@@@@@@@@%=..      ......#@@@@@#....-%+...   .        ...+@@@@@@%..:@#.....*%@@@@
    %%%%%@@@@@@@@@@@@@@@@@@@@@@@%=..      .....#@@@@@@@:.....#*..            ..-@@@@@*:*...*%.....+%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@%=..         .-@@@@@@%*=.....:#*.           ...%@#=.:=#*...=@:. ..+%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@%=...        .*@@@#-.:*=......:@+...         .++.:*@@@@-...-@:. ..+%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@%+...  .     .#%:.:#@@@=...  ..+@:..         .#@@@@@@@%....=@:....+%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@*..         :#+#@@@@@@:...  ...%*..        .-%@@@@@@@=.  .+%.....*@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@#-..        :#@@@@@@@#....  ...=#:.       ..=@@@@@@@#.. ..*+....:#@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@+.         .#@@@@@@@=.     ....%-.      ...+@@@@@@%..  ..%:....-%@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%:.......  .*@@@@@@#:.     ....*=.      ...*@@@@@%......-*.....*@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@#.......  .+@@@@@@:..      . .==. .     ..*@@@@+... ...+:....-%@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#......  .:@@@@@....   .    .-=.     . ..#@@+........:=.....%%@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%*:..... ..#@%+.....       ..:=.       ..=:..:::::::-=:....==--#%@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@#:.......-+::---===++==+++++-..........:--:::....... ......:*%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%=............................ ...................   ....-%@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%*:......     ..-*+-:....................     .   ....:#%@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%*:.......  ...:+-:=+*#%%%###***++++..............:+%@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%*:............=#-............:*-.............:*%@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%=............=#*:......:+#-.............-#%@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#=:...........=+****+-............:=#%@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#+-:......................-+#%@@@@@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%%%#*+=-::::::-=+#%%%%@@@@@@@@@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%+**##%%%@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
============================================================================================================
============================================================================================================
*/














